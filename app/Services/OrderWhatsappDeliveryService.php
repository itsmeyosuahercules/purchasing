<?php

namespace App\Services;

use App\Exceptions\WatzapDeliveryException;
use App\Models\Order;
use Illuminate\Support\Facades\URL;

class OrderWhatsappDeliveryService
{
    public function __construct(
        private OrderTemplateService $templateService,
        private OrderPdfService $pdfService,
        private WatzapService $watzapService,
    ) {}

    public function canDeliver(): bool
    {
        return (bool) config('watzap.enabled') && $this->watzapService->isConfigured();
    }

    /**
     * URL signed sementara agar server WatZap bisa mengunduh PDF tanpa login.
     */
    public function signedPdfUrl(Order $order): string
    {
        $ttl = (int) config('watzap.pdf_url_ttl_minutes', 30);
        $filename = $this->pdfService->filename($order);

        return URL::temporarySignedRoute(
            'orders.pdf.delivery',
            now()->addMinutes($ttl),
            ['order' => $order->id, 'filename' => $filename],
        );
    }

    /**
     * Kirim template WhatsApp + lampiran PDF ke supplier.
     */
    public function sendToSupplier(Order $order): void
    {
        if (! $this->canDeliver()) {
            throw new WatzapDeliveryException('Pengiriman WatZap tidak aktif atau belum dikonfigurasi.');
        }

        if ($order->status->value !== 'approved') {
            throw new WatzapDeliveryException('WhatsApp hanya bisa dikirim untuk pesanan yang sudah disetujui.');
        }

        $order->loadMissing('supplier');

        $message = $this->templateService->getWhatsappTemplate($order);
        $phone = $order->supplier->whatsapp;

        if ($this->shouldAttachPdf()) {
            $this->pdfService->ensureDeliveryCache($order);

            $textSent = false;

            try {
                $this->watzapService->sendText($phone, $message);
                $textSent = true;

                $delay = (int) config('watzap.send_delay_seconds', 2);
                if ($delay > 0) {
                    sleep($delay);
                }

                $this->watzapService->sendFileUrl(
                    $phone,
                    $this->signedPdfUrl($order),
                    filename: $this->pdfService->filename($order),
                );
            } catch (\Throwable $e) {
                if ($textSent) {
                    $order->forceFill([
                        'supplier_whatsapp_sent_at' => now(),
                        'supplier_whatsapp_error' => mb_substr(
                            'Teks terkirim, PDF gagal: '.$e->getMessage(),
                            0,
                            1000,
                        ),
                    ])->save();
                }

                throw $e;
            }
        } else {
            $this->watzapService->sendText($phone, $message);
        }

        $order->forceFill([
            'supplier_whatsapp_sent_at' => now(),
            'supplier_whatsapp_error' => null,
        ])->save();
    }

    /**
     * WatZap hanya bisa fetch file dari URL HTTPS publik (bukan localhost / .test).
     */
    public function shouldAttachPdf(): bool
    {
        $configured = config('watzap.attach_pdf');

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return $this->pdfUrlIsDeliverableByWatzap();
    }

    private function pdfUrlIsDeliverableByWatzap(): bool
    {
        $appUrl = strtolower((string) config('app.url', ''));
        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));

        if ($appUrl === '' || $host === '') {
            return false;
        }

        if (! str_starts_with($appUrl, 'https://')) {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return ! str_ends_with($host, '.test')
            && ! str_ends_with($host, '.local')
            && ! str_ends_with($host, '.localhost');
    }
}
