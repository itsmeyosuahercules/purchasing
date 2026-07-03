<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\WatzapDeliveryException;
use App\Jobs\SendOrderWhatsappJob;
use App\Mail\OrderCopyToAdminMail;
use App\Mail\OrderToSupplierMail;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderApprovalService
{
    public function __construct(
        private OrderTemplateService $templateService,
        private WhatsappLinkService $whatsappLinkService,
        private OrderWhatsappDeliveryService $whatsappDeliveryService,
        private OrderPdfService $pdfService,
    ) {}

    public function approve(Order $order, User $admin): Order
    {
        if (! $order->isPending()) {
            throw new \RuntimeException('Pesanan ini sudah diproses.');
        }

        $this->extendExecutionTime();

        $order->load(['supplier', 'items', 'user']);

        $whatsappBody = $this->templateService->getWhatsappTemplate($order);
        $whatsappLink = $this->whatsappLinkService->generate(
            $order->supplier->whatsapp,
            $whatsappBody,
        );

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

        $order->refresh()->load(['supplier', 'items', 'user', 'approver']);

        $emailBody = $this->templateService->getEmailTemplate($order);
        $this->pdfService->ensureDeliveryCache($order);

        $this->dispatchEmails($order, $emailBody);
        $this->sendWhatsapp($order);

        return $order->fresh(['supplier', 'items', 'user', 'approver']);
    }

    public function resendEmail(Order $order): Order
    {
        if ($order->status !== OrderStatus::Approved) {
            throw new \RuntimeException('Email hanya bisa dikirim ulang untuk pesanan yang sudah disetujui.');
        }

        $this->extendExecutionTime();

        $order->load(['supplier', 'items', 'user', 'approver']);
        $this->pdfService->ensureDeliveryCache($order);
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
            throw new \RuntimeException('WhatsApp masih diproses. Jangan klik ulang — tunggu halaman selesai loading.');
        }

        $order->forceFill(['supplier_whatsapp_error' => null])->save();

        $this->sendWhatsapp($order, force: true);

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
        $supplierEmail = trim((string) $order->supplier->email);

        if ($supplierEmail === '') {
            $message = 'Email supplier kosong. Isi email di data supplier terlebih dahulu.';

            Log::error('Gagal mengirim email pesanan', [
                'order_id' => $order->id,
                'message' => $message,
            ]);

            if ($rethrow) {
                throw new \RuntimeException($message);
            }

            return false;
        }

        try {
            Mail::to($supplierEmail)->send(new OrderToSupplierMail($order, $emailBody));
            $order->forceFill(['supplier_emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email pesanan ke supplier', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            if ($rethrow) {
                throw new \RuntimeException('Gagal mengirim email: '.$e->getMessage(), 0, $e);
            }

            return false;
        }

        $adminEmail = trim((string) Setting::get('admin_email', ''));

        if ($adminEmail !== '') {
            try {
                Mail::to($adminEmail)->send(new OrderCopyToAdminMail($order, $emailBody));
            } catch (\Throwable $e) {
                Log::warning('Email supplier terkirim, salinan admin gagal', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    private function sendWhatsapp(Order $order, bool $force = false): void
    {
        if (! $this->whatsappDeliveryService->canDeliver()) {
            return;
        }

        $this->extendExecutionTime();

        $order->loadMissing(['supplier', 'items']);

        $lockKey = 'watzap-sending:'.$order->id;

        if (Cache::has($lockKey)) {
            throw new \RuntimeException('WhatsApp masih diproses. Jangan klik ulang — tunggu halaman selesai loading.');
        }

        Cache::put($lockKey, true, now()->addMinutes(3));

        Log::info('WhatsApp kirim sinkron dimulai', [
            'order_id' => $order->id,
            'force' => $force,
        ]);

        try {
            (new SendOrderWhatsappJob($order->id, $force))->handle($this->whatsappDeliveryService);
        } catch (WatzapDeliveryException $e) {
            Log::error('Gagal mengirim WhatsApp pesanan', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            throw new \RuntimeException($e->getMessage(), 0, $e);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function whatsappSendInProgress(int $orderId): bool
    {
        return Cache::has('watzap-sending:'.$orderId);
    }

    private function extendExecutionTime(): void
    {
        $seconds = max(120, (int) config('watzap.file_timeout', 90) + 90);

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }
}
