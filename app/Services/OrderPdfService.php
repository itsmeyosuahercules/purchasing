<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\RupiahTerbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
}
