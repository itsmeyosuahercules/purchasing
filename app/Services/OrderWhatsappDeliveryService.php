<?php

namespace App\Services;

use App\Exceptions\WatzapDeliveryException;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

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

    public function shortPdfDownloadUrl(Order $order): string
    {
        return $this->pdfService->createShortDownloadUrl($order);
    }

    /**
     * @deprecated Gunakan shortPdfDownloadUrl()
     */
    public function signedPdfDownloadUrl(Order $order): string
    {
        return $this->shortPdfDownloadUrl($order);
    }

    /**
     * @deprecated Gunakan shortPdfDownloadUrl()
     */
    public function signedPdfUrl(Order $order): string
    {
        return $this->shortPdfDownloadUrl($order);
    }

    public function sendToSupplier(Order $order): void
    {
        if (! $this->canDeliver()) {
            throw new WatzapDeliveryException('Pengiriman WatZap tidak aktif atau belum dikonfigurasi.');
        }

        if ($order->status->value !== 'approved') {
            throw new WatzapDeliveryException('WhatsApp hanya bisa dikirim untuk pesanan yang sudah disetujui.');
        }

        $order->loadMissing('supplier');

        $phone = $order->supplier->whatsapp;
        $downloadUrl = null;

        if ($this->shouldAttachPdf()) {
            if ($this->usesLinkSendMode()) {
                $downloadUrl = $this->shortPdfDownloadUrl($order);
                $this->sendWithPdfDownloadLink($order, $phone, $downloadUrl);
            } else {
                $message = $this->templateService->getWhatsappTemplate($order);
                $this->sendWithPdfAttachment($order, $phone, $message);
            }
        } else {
            $message = $this->templateService->getWhatsappTemplate($order);
            $this->watzapService->sendText($phone, $message);
        }

        $this->sendOwnerCopy($order, $downloadUrl);

        $order->forceFill([
            'supplier_whatsapp_sent_at' => now(),
            'supplier_whatsapp_error' => null,
        ])->save();
    }

    /**
     * Mode link: 1x send_message dengan URL unduh PDF (auto-download saat diklik).
     */
    private function sendWithPdfDownloadLink(Order $order, string $phone, ?string $downloadUrl = null): void
    {
        $downloadUrl ??= $this->shortPdfDownloadUrl($order);
        $message = $this->templateService->getWhatsappTemplate($order, $downloadUrl);

        if (! str_contains($message, $downloadUrl)) {
            $days = (int) config('watzap.pdf_link_ttl_days', 7);
            $message = rtrim($message)."\n\nUnduh PO {$order->order_number}:\n{$downloadUrl}\n(Link aktif {$days} hari)";
        }

        $this->watzapService->sendText($phone, $message);
    }

    private function sendOwnerCopy(Order $order, ?string $downloadUrl = null): void
    {
        $ownerPhone = trim((string) Setting::get('whatsapp_contact', ''));

        if ($ownerPhone === '') {
            return;
        }

        $supplierPhone = trim((string) ($order->supplier->whatsapp ?? ''));

        if ($supplierPhone !== ''
            && $this->watzapService->normalizePhone($ownerPhone) === $this->watzapService->normalizePhone($supplierPhone)) {
            return;
        }

        try {
            if ($downloadUrl === null && $this->shouldAttachPdf() && $this->usesLinkSendMode()) {
                $downloadUrl = $this->shortPdfDownloadUrl($order);
            }

            $message = $this->templateService->getOwnerWhatsappTemplate($order, $downloadUrl);

            if ($downloadUrl !== null && ! str_contains($message, $downloadUrl)) {
                $days = (int) config('watzap.pdf_link_ttl_days', 7);
                $message = rtrim($message)."\n\nUnduh PO {$order->order_number}:\n{$downloadUrl}\n(Link aktif {$days} hari)";
            }

            $this->watzapService->sendText($ownerPhone, $message);
        } catch (\Throwable $e) {
            Log::warning('Salinan WhatsApp ke owner gagal', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mode combined/separate: WatZap fetch PDF via send_file_url (lambat).
     */
    private function sendWithPdfAttachment(Order $order, string $phone, string $message): void
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

    private function usesLinkSendMode(): bool
    {
        return strtolower((string) config('watzap.send_mode', 'link')) === 'link';
    }

    private function usesCombinedSendMode(): bool
    {
        return strtolower((string) config('watzap.send_mode', 'link')) === 'combined';
    }

    private function isTimeoutException(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'timeout') || str_contains($msg, 'timed out');
    }

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
