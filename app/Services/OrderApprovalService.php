<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\SendOrderWhatsappJob;
use App\Mail\OrderCopyToAdminMail;
use App\Mail\OrderToSupplierMail;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderWhatsappDeliveryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;

class OrderApprovalService
{
    public function __construct(
        private OrderTemplateService $templateService,
        private WhatsappLinkService $whatsappLinkService,
        private OrderWhatsappDeliveryService $whatsappDeliveryService,
    ) {}

    public function approve(Order $order, User $admin): Order
    {
        if (! $order->isPending()) {
            throw new \RuntimeException('Pesanan ini sudah diproses.');
        }

        $order->load(['supplier', 'items', 'user']);

        $emailBody = $this->templateService->getEmailTemplate($order);
        $whatsappBody = $this->templateService->getWhatsappTemplate($order);
        $whatsappLink = $this->whatsappLinkService->generate(
            $order->supplier->whatsapp,
            $whatsappBody,
        );

        // Status di-commit dulu; pengiriman email tidak boleh ikut rollback transaksi.
        DB::transaction(function () use ($order, $admin, $whatsappLink) {
            $validityDays = (int) Setting::get('po_validity_days', 30);
            $deliveryDays = (int) Setting::get('default_delivery_days', 14);

            $order->update([
                'status' => OrderStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'valid_until' => $order->valid_until ?? now()->addDays($validityDays)->toDateString(),
                'delivery_date' => $order->delivery_date ?? now()->addDays($deliveryDays)->toDateString(),
                'whatsapp_link' => $whatsappLink,
            ]);
        });

        $this->dispatchEmails($order, $emailBody);
        $this->dispatchWhatsapp($order);

        return $order->fresh(['supplier', 'items', 'user', 'approver']);
    }

    public function resendEmail(Order $order): Order
    {
        if ($order->status !== OrderStatus::Approved) {
            throw new \RuntimeException('Email hanya bisa dikirim ulang untuk pesanan yang sudah disetujui.');
        }

        $order->load(['supplier', 'items', 'user']);
        $emailBody = $this->templateService->getEmailTemplate($order);
        $this->dispatchEmails($order, $emailBody, rethrow: true);

        return $order->fresh(['supplier', 'items', 'user', 'approver']);
    }

    public function resendWhatsapp(Order $order): Order
    {
        if ($order->status !== OrderStatus::Approved) {
            throw new \RuntimeException('WhatsApp hanya bisa dikirim ulang untuk pesanan yang sudah disetujui.');
        }

        if (! $this->whatsappDeliveryService->canDeliver()) {
            throw new \RuntimeException('WatZap belum diaktifkan atau API Key / Number Key belum dikonfigurasi.');
        }

        if ($this->whatsappSendInProgress($order->id)) {
            throw new \RuntimeException('WhatsApp masih diproses. Tunggu hingga selesai (~1–2 menit) lalu refresh halaman sebelum kirim ulang.');
        }

        $order->forceFill(['supplier_whatsapp_error' => null])->save();

        $this->dispatchWhatsappJob($order->id, force: true);

        return $order->fresh(['supplier', 'items', 'user', 'approver']);
    }

    public function reject(Order $order, User $admin, ?string $reason = null): Order
    {
        if (! $order->isPending()) {
            throw new \RuntimeException('Pesanan ini sudah diproses.');
        }

        $order->update([
            'status' => OrderStatus::Rejected,
            'approved_by' => $admin->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $order->fresh(['supplier', 'items', 'user']);
    }

    private function dispatchEmails(Order $order, string $emailBody, bool $rethrow = false): bool
    {
        try {
            Mail::to($order->supplier->email)->send(new OrderToSupplierMail($order, $emailBody));

            $adminEmail = Setting::get('admin_email');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new OrderCopyToAdminMail($order, $emailBody));
            }

            $order->forceFill(['supplier_emailed_at' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email pesanan', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            if ($rethrow) {
                throw new \RuntimeException('Gagal mengirim email: '.$e->getMessage(), 0, $e);
            }

            return false;
        }
    }

    private function dispatchWhatsapp(Order $order): void
    {
        if (! $this->whatsappDeliveryService->canDeliver()) {
            return;
        }

        $this->dispatchWhatsappJob($order->id);
    }

    private function dispatchWhatsappJob(int $orderId, bool $force = false): void
    {
        if ($this->whatsappSendInProgress($orderId)) {
            Log::info('WhatsApp dilewati: pengiriman sebelumnya masih berjalan', [
                'order_id' => $orderId,
            ]);

            return;
        }

        Cache::put(SendOrderWhatsappJob::sendingLockKey($orderId), now()->toIso8601String(), now()->addMinutes(5));

        Log::info('WhatsApp diproses di background (proses terpisah)', [
            'order_id' => $orderId,
            'force' => $force,
        ]);

        if (app()->runningUnitTests()) {
            try {
                (new SendOrderWhatsappJob($orderId, $force))->handle($this->whatsappDeliveryService);
            } finally {
                Cache::forget(SendOrderWhatsappJob::sendingLockKey($orderId));
            }

            return;
        }

        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'orders:send-whatsapp',
            (string) $orderId,
        ];

        if ($force) {
            $command[] = '--force';
        }

        Process::path(base_path())
            ->timeout(null)
            ->start($command);
    }

    private function whatsappSendInProgress(int $orderId): bool
    {
        return Cache::has(SendOrderWhatsappJob::sendingLockKey($orderId));
    }
}
