<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderWhatsappJob;
use App\Services\OrderWhatsappDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendOrderWhatsappCommand extends Command
{
    protected $signature = 'orders:send-whatsapp {orderId : ID pesanan} {--force : Kirim ulang meski sudah pernah terkirim}';

    protected $description = 'Kirim WhatsApp PO ke supplier (proses terpisah dari request web)';

    public function handle(OrderWhatsappDeliveryService $deliveryService): int
    {
        $orderId = (int) $this->argument('orderId');
        $force = (bool) $this->option('force');
        $lockKey = SendOrderWhatsappJob::sendingLockKey($orderId);

        set_time_limit((int) config('watzap.file_timeout', 120) + 120);

        try {
            (new SendOrderWhatsappJob($orderId, $force))->handle($deliveryService);
        } catch (\Throwable $e) {
            Log::error('WhatsApp command gagal', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            Cache::forget($lockKey);
        }

        return self::SUCCESS;
    }
}
