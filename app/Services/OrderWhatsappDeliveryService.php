<?php

namespace App\Services;

use App\Exceptions\WatzapDeliveryException;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $publication = $this->pdfService->publishForWatzap($order);
            $pdfToken = $publication['token'];
            $pdfUrl = $publication['url'];

            $textSent = false;

            try {
                $this->verifyPdfUrlAccessible($pdfUrl, $publication['path']);

                $this->watzapService->sendText($phone, $message);
                $textSent = true;

                $delay = (int) config('watzap.send_delay_seconds', 2);
                if ($delay > 0) {
                    sleep($delay);
                }

                $this->watzapService->sendFileUrl(
                    $phone,
                    $pdfUrl,
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
            } finally {
                $this->pdfService->cleanupWatzapPublication($pdfToken);
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

    /**
     * Pastikan PDF benar-benar bisa di-fetch sebelum WatZap mencoba (cegah error 1005).
     */
    private function verifyPdfUrlAccessible(string $pdfUrl, string $absolutePath): void
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 100) {
            throw new WatzapDeliveryException('File PDF tidak ada atau rusak di server.');
        }

        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => true])
                ->get($pdfUrl);
        } catch (\Throwable $e) {
            Log::warning('Self-check URL PDF WatZap gagal', [
                'url' => $pdfUrl,
                'message' => $e->getMessage(),
            ]);

            throw new WatzapDeliveryException(
                'PDF tidak dapat diakses dari URL publik ('.$pdfUrl.'): '.$e->getMessage()
                .'. Periksa APP_URL dan izin folder public/watzap-delivery.',
            );
        }

        if (! $response->successful()) {
            throw new WatzapDeliveryException(
                'PDF URL mengembalikan HTTP '.$response->status()
                .'. Buka '.$pdfUrl.' di browser untuk diagnosa.',
            );
        }

        $body = $response->body();

        if (strlen($body) < 100 || ! str_starts_with($body, '%PDF')) {
            throw new WatzapDeliveryException(
                'PDF URL tidak mengembalikan file PDF valid. Kemungkinan APP_URL salah atau server memblokir akses eksternal.',
            );
        }
    }
}
