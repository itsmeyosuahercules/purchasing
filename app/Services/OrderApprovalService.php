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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $this->sendWhatsapp($order);

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

    private function sendWhatsapp(Order $order, bool $force = false): void
    {
        if (! $this->whatsappDeliveryService->canDeliver()) {
            return;
        }

        $order->loadMissing(['supplier', 'items']);

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
        }
    }
}
