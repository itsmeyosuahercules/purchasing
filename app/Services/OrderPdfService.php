<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\RupiahTerbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderPdfService
{
    /**
     * @param  bool  $forEmployee  Sembunyikan harga & kontak supplier (versi karyawan).
     */
    public function make(Order $order, bool $forEmployee = false): DomPdfDocument
    {
        $order->loadMissing(['supplier', 'items', 'user', 'approver']);

        // Margin diatur lewat CSS @page + body padding di pdf/order.blade.php (mm).
        // setOption margin-* di DomPDF pakai point — rawan salah baca jadi mepet.
        return Pdf::loadView('pdf.order', [
            'order' => $order,
            'companyName' => Setting::get('company_name', config('app.name')),
            'companyEmail' => Setting::get('company_email'),
            'wechatContact' => Setting::get('wechat_contact'),
            'whatsappContact' => Setting::get('whatsapp_contact'),
            'shipTo' => Setting::get('ship_to', 'Will be notified before delivery'),
            'paymentTerms' => Setting::get('payment_terms', 'As Usual'),
            'shippingMethod' => Setting::get('shipping_method', 'As Usual'),
            'incoterms' => Setting::get('incoterms', 'Exworks'),
            'currency' => Setting::get('currency', 'IDR'),
            'termsConditions' => Setting::get('terms_conditions', ''),
            'amountInWords' => RupiahTerbilang::format($order->total()),
            'forEmployee' => $forEmployee,
        ])->setPaper('a4', 'portrait');
    }

    public function filename(Order $order): string
    {
        return "purchase-order-{$order->order_number}.pdf";
    }

    public function deliveryCachePath(Order $order): string
    {
        return 'watzap-delivery/'.$order->id.'/'.$this->filename($order);
    }

    /**
     * Generate PDF ke disk sebelum WatZap fetch — hindari timeout/error saat request eksternal.
     */
    public function ensureDeliveryCache(Order $order, bool $force = false): void
    {
        $order->loadMissing(['supplier', 'items', 'user', 'approver']);

        $path = $this->deliveryCachePath($order);

        if (! $force && Storage::disk('local')->exists($path) && Storage::disk('local')->size($path) >= 100) {
            return;
        }

        try {
            $pdf = $this->make($order)->output();
            Storage::disk('local')->makeDirectory('watzap-delivery/'.$order->id);
            Storage::disk('local')->put($path, $pdf);
        } catch (\Throwable $e) {
            Log::error('Gagal men-cache PDF untuk WatZap', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function downloadFromDeliveryCache(Order $order): ?StreamedResponse
    {
        $path = $this->deliveryCachePath($order);

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->download(
            $path,
            $this->filename($order),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Buat link pendek /po/{token} untuk unduh PDF via WhatsApp.
     */
    public function createShortDownloadUrl(Order $order): string
    {
        $this->ensureDeliveryCache($order);

        $token = strtolower(Str::random(10));
        $days = max(1, (int) config('watzap.pdf_link_ttl_days', 7));

        Cache::put($this->downloadTokenCacheKey($token), $order->id, now()->addDays($days));

        return rtrim((string) config('app.url'), '/').'/po/'.$token;
    }

    public function findOrderByDownloadToken(string $token): ?Order
    {
        $token = strtolower($token);

        if (! preg_match('/^[a-z0-9]{8,12}$/', $token)) {
            return null;
        }

        $orderId = Cache::get($this->downloadTokenCacheKey($token));

        if (! $orderId) {
            return null;
        }

        return Order::query()->find($orderId);
    }

    private function downloadTokenCacheKey(string $token): string
    {
        return 'po-download:'.strtolower($token);
    }

    /**
     * Publish PDF as a static public file for WatZap (no signed URL / PHP route).
     * URL diakhiri nama file ramah pengguna; folder token acak mencegah tebak URL.
     *
     * @return array{relative_path: string, url: string, path: string, filename: string}
     */
    public function publishForWatzap(Order $order): array
    {
        $order->loadMissing(['supplier', 'items', 'user', 'approver']);

        $filename = $this->filename($order);
        $token = Str::random(32);
        $relativePath = $token.'/'.$filename;
        $dir = public_path('watzap-delivery'.DIRECTORY_SEPARATOR.$token);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Folder public/watzap-delivery tidak dapat dibuat.');
        }

        $absolutePath = $dir.DIRECTORY_SEPARATOR.$filename;

        try {
            $pdf = $this->make($order)->output();
            file_put_contents($absolutePath, $pdf);
        } catch (\Throwable $e) {
            Log::error('Gagal publish PDF statis untuk WatZap', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (! is_file($absolutePath) || filesize($absolutePath) < 100) {
            @unlink($absolutePath);

            throw new \RuntimeException('File PDF WatZap kosong atau tidak valid.');
        }

        return [
            'relative_path' => $relativePath,
            'filename' => $filename,
            'url' => rtrim((string) config('app.url'), '/').'/watzap-delivery/'.$relativePath,
            'path' => $absolutePath,
        ];
    }

    public function cleanupWatzapPublication(string $relativePath): void
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');

        if (str_contains($relativePath, '..')) {
            return;
        }

        $path = public_path('watzap-delivery'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if (is_file($path)) {
            @unlink($path);
        }

        $tokenDir = dirname($path);
        $deliveryRoot = realpath(public_path('watzap-delivery'));

        if ($deliveryRoot && is_dir($tokenDir) && str_starts_with(realpath($tokenDir) ?: '', $deliveryRoot)) {
            @rmdir($tokenDir);
        }
    }
}
