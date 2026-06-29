<?php

namespace App\Jobs;

use App\Exceptions\WatzapDeliveryException;
use App\Models\Order;
use App\Services\OrderWhatsappDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderWhatsappJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(OrderWhatsappDeliveryService $deliveryService): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        if (! $deliveryService->canDeliver()) {
            return;
        }

        try {
            $deliveryService->sendToSupplier($order);
        } catch (WatzapDeliveryException $e) {
            $this->recordFailure($order, $e->getMessage());
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

        $this->recordFailure($order, $exception?->getMessage() ?? 'Gagal mengirim WhatsApp.');
    }

    private function recordFailure(Order $order, string $message): void
    {
        $order->forceFill([
            'supplier_whatsapp_error' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
