<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderWhatsappJob;
use App\Services\OrderWhatsappDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendOrderWhatsappCommand extends Command
{
    protected $signature = 'orders:send-whatsapp {orderId : ID pesanan} {--force : Kirim ulang meski sudah pernah terkirim}';

    protected $description = 'Kirim WhatsApp PO ke supplier (manual / debug)';

    public function handle(OrderWhatsappDeliveryService $deliveryService): int
    {
        $orderId = (int) $this->argument('orderId');
        $force = (bool) $this->option('force');

        try {
            (new SendOrderWhatsappJob($orderId, $force))->handle($deliveryService);
        } catch (\Throwable $e) {
            Log::error('WhatsApp command gagal', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Selesai. Cek log dan status pesanan.');

        return self::SUCCESS;
    }
}
