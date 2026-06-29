<?php

namespace App\Jobs;

use App\Exceptions\WatzapDeliveryException;
use App\Models\Order;
use App\Services\OrderWhatsappDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class SendOrderWhatsappJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 90;

    public function __construct(
        public int $orderId,
        public bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        if ($this->force) {
            return 'watzap-order-'.$this->orderId.'-'.microtime(true);
        }

        return 'watzap-order-'.$this->orderId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('watzap-order-'.$this->orderId))
                ->expireAfter(300),
        ];
    }

    public function handle(OrderWhatsappDeliveryService $deliveryService): void
    {
        Log::info('WhatsApp job mulai', [
            'order_id' => $this->orderId,
            'force' => $this->force,
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

    public function failed(?\Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        Log::error('Gagal mengirim WhatsApp pesanan setelah semua percobaan', [
            'order_id' => $order->id,
            'message' => $exception?->getMessage(),
        ]);

        if (blank($order->supplier_whatsapp_error)) {
            $this->recordFailure($order, $exception?->getMessage() ?? 'Gagal mengirim WhatsApp.');
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
