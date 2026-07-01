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
            $this->sendWithPdf($order, $phone, $message);
        } else {
            $this->watzapService->sendText($phone, $message);
        }

        $order->forceFill([
            'supplier_whatsapp_sent_at' => now(),
            'supplier_whatsapp_error' => null,
        ])->save();
    }

    private function sendWithPdf(Order $order, string $phone, string $message): void
    {
        $publication = $this->pdfService->publishForWatzap($order);
        $pdfRelativePath = $publication['relative_path'];
        $pdfUrl = $publication['url'];

        $textSent = false;

        try {
            $this->verifyPdfOnDisk($publication['path']);

            if ($this->usesCombinedSendMode()) {
                $this->watzapService->sendFileUrl(
                    $phone,
                    $pdfUrl,
                    message: $message,
                    filename: $publication['filename'],
                );
            } else {
                $this->watzapService->sendText($phone, $message);
                $textSent = true;

                $delay = (int) config('watzap.send_delay_seconds', 3);
                if ($delay > 0) {
                    sleep($delay);
                }

                $this->watzapService->sendFileUrl(
                    $phone,
                    $pdfUrl,
                    filename: $publication['filename'],
                );
            }
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
            } elseif ($this->isTimeoutException($e)) {
                $order->forceFill([
                    'supplier_whatsapp_sent_at' => now(),
                    'supplier_whatsapp_error' => mb_substr(
                        'WatZap timeout — pesan/PDF mungkin sudah terkirim. Cek WhatsApp supplier sebelum kirim ulang.',
                        0,
                        1000,
                    ),
                ])->save();

                return;
            }

            throw $e;
        } finally {
            $this->pdfService->cleanupWatzapPublication($pdfRelativePath);
        }
    }

    private function usesCombinedSendMode(): bool
    {
        return strtolower((string) config('watzap.send_mode', 'combined')) !== 'separate';
    }

    private function isTimeoutException(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'timeout') || str_contains($msg, 'timed out');
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

    private function verifyPdfOnDisk(string $absolutePath): void
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 100) {
            throw new WatzapDeliveryException('File PDF tidak ada atau rusak di server.');
        }
    }
}
