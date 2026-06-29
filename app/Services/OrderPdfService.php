<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\RupiahTerbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
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
    public function ensureDeliveryCache(Order $order): void
    {
        $order->loadMissing(['supplier', 'items', 'user', 'approver']);

        $path = $this->deliveryCachePath($order);

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
     * Publish PDF as a static public file for WatZap (no signed URL / PHP route).
     *
     * @return array{token: string, url: string, path: string}
     */
    public function publishForWatzap(Order $order): array
    {
        $order->loadMissing(['supplier', 'items', 'user', 'approver']);

        $token = Str::random(48);
        $dir = public_path('watzap-delivery');

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Folder public/watzap-delivery tidak dapat dibuat.');
        }

        $absolutePath = $dir.DIRECTORY_SEPARATOR.$token.'.pdf';

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
            'token' => $token,
            'url' => rtrim((string) config('app.url'), '/').'/watzap-delivery/'.$token.'.pdf',
            'path' => $absolutePath,
        ];
    }

    public function cleanupWatzapPublication(string $token): void
    {
        $path = public_path('watzap-delivery'.DIRECTORY_SEPARATOR.$token.'.pdf');

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
