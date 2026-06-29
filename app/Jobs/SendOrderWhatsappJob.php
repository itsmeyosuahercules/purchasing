<?php

namespace App\Jobs;

use App\Exceptions\WatzapDeliveryException;
use App\Models\Order;
use App\Services\OrderWhatsappDeliveryService;
use Illuminate\Support\Facades\Log;

/**
 * Runner pengiriman WhatsApp — dipanggil langsung (sinkron) dari web request.
 */
class SendOrderWhatsappJob
{
    public function __construct(
        public int $orderId,
        public bool $force = false,
    ) {}

    public function handle(OrderWhatsappDeliveryService $deliveryService): void
    {
        set_time_limit((int) config('watzap.file_timeout', 90) + 60);

        Log::info('WhatsApp job mulai', [
            'order_id' => $this->orderId,
            'force' => $this->force,
            'pid' => getmypid(),
        ]);

        $order = Order::query()->find($this->orderId);

        if (! $order) {
            Log::warning('WhatsApp job dibatalkan: pesanan tidak ditemukan', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        if (! $deliveryService->canDeliver()) {
            Log::warning('WhatsApp job dibatalkan: WatZap tidak aktif / belum dikonfigurasi', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $order->refresh();

        if (! $this->force && $this->alreadyDelivered($order)) {
            Log::info('WhatsApp job dilewati: sudah terkirim sebelumnya', [
                'order_id' => $this->orderId,
                'sent_at' => $order->supplier_whatsapp_sent_at,
            ]);

            return;
        }

        if (! $this->force && $this->isPartialDelivery($order)) {
            Log::info('WhatsApp job dilewati: teks sudah terkirim, PDF gagal sebelumnya', [
                'order_id' => $this->orderId,
                'error' => $order->supplier_whatsapp_error,
            ]);

            return;
        }

        try {
            $deliveryService->sendToSupplier($order);

            $order->refresh();

            Log::info('WhatsApp job selesai', [
                'order_id' => $this->orderId,
                'sent_at' => $order->supplier_whatsapp_sent_at,
                'error' => $order->supplier_whatsapp_error,
                'pid' => getmypid(),
            ]);
        } catch (WatzapDeliveryException $e) {
            $order->refresh();

            if (blank($order->supplier_whatsapp_error)) {
                $this->recordFailure($order, $e->getMessage());
            }

            if ($this->isPartialDelivery($order)) {
                Log::warning('WhatsApp job selesai sebagian (teks OK, PDF gagal)', [
                    'order_id' => $this->orderId,
                    'error' => $order->supplier_whatsapp_error,
                ]);

                return;
            }

            Log::error('WhatsApp job gagal', [
                'order_id' => $this->orderId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function alreadyDelivered(Order $order): bool
    {
        return $order->supplier_whatsapp_sent_at !== null
            && blank($order->supplier_whatsapp_error);
    }

    private function isPartialDelivery(Order $order): bool
    {
        return $order->supplier_whatsapp_sent_at !== null
            && filled($order->supplier_whatsapp_error);
    }

    private function recordFailure(Order $order, string $message): void
    {
        $order->forceFill([
            'supplier_whatsapp_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
