<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\SendOrderWhatsappJob;
use App\Models\Order;
use App\Services\OrderWhatsappDeliveryService;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WatzapIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'watzap.enabled' => true,
            'watzap.api_key' => 'test-api-key',
            'watzap.number_key' => 'test-number-key',
            'watzap.base_url' => 'https://api.watzap.id/v1',
            'watzap.attach_pdf' => true,
            'app.url' => 'https://purchasing.example.com',
            'watzap.send_delay_seconds' => 0,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.com',
            'password' => 'password', 'role' => UserRole::Admin, 'is_active' => true,
        ]);
    }

    private function approvedOrder(): Order
    {
        $admin = $this->admin();

        $employee = User::create([
            'name' => 'Karyawan', 'username' => 'karyawan', 'email' => null,
            'password' => 'password', 'role' => UserRole::Employee, 'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'real_name' => 'PT Supplier', 'alias_name' => 'Supplier X',
            'email' => 'sup@test.com', 'whatsapp' => '08123456789', 'is_active' => true,
        ]);

        $product = $supplier->products()->create([
            'name' => 'Beras', 'price' => 10000, 'unit' => 'kg', 'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'PO-WA-0001',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Beras',
            'quantity' => 2,
            'unit' => 'kg',
            'price' => 10000,
        ]);

        return $order->fresh(['supplier', 'items']);
    }

    public function test_approve_dispatches_whatsapp_job_when_watzap_enabled(): void
    {
        Queue::fake();

        $employee = User::create([
            'name' => 'Karyawan', 'username' => 'karyawan', 'password' => 'password',
            'role' => UserRole::Employee, 'is_active' => true,
        ]);
        $admin = $this->admin();
        $supplier = Supplier::create([
            'real_name' => 'PT Supplier', 'alias_name' => 'Supplier X',
            'email' => 'sup@test.com', 'whatsapp' => '08123456789', 'is_active' => true,
        ]);
        $product = $supplier->products()->create([
            'name' => 'Beras', 'price' => 10000, 'unit' => 'kg', 'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'PO-WA-PENDING',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Beras',
            'quantity' => 1,
            'unit' => 'kg',
            'price' => 10000,
        ]);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        Queue::assertPushed(SendOrderWhatsappJob::class, fn (SendOrderWhatsappJob $job) => $job->orderId === $order->id);
    }

    public function test_resend_whatsapp_calls_watzap_api_with_pdf_url(): void
    {
        Http::fake([
            'https://api.watzap.id/v1/send_message' => Http::response([
                'status' => true,
                'message' => 'success',
            ]),
            'https://api.watzap.id/v1/send_file_url' => Http::response([
                'status' => true,
                'message' => 'success',
            ]),
        ]);

        $order = $this->approvedOrder();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/resend-whatsapp")
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertNotNull($order->supplier_whatsapp_sent_at);
        $this->assertNull($order->supplier_whatsapp_error);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.watzap.id/v1/send_message'
                && str_contains($body['message'], 'PT Supplier');
        });

        Http::assertSent(function ($request) use ($order) {
            $body = $request->data();
            $filename = 'purchase-order-'.$order->order_number.'.pdf';

            return $request->url() === 'https://api.watzap.id/v1/send_file_url'
                && $body['phone_no'] === '628123456789'
                && str_contains($body['url'], $filename)
                && ($body['filename'] ?? '') === $filename
                && ! isset($body['message']);
        });
    }

    public function test_local_mode_sends_text_only_without_pdf_url(): void
    {
        config(['watzap.attach_pdf' => false]);

        Http::fake([
            'https://api.watzap.id/v1/send_message' => Http::response([
                'status' => true,
                'message' => 'success',
            ]),
        ]);

        $order = $this->approvedOrder();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/resend-whatsapp")
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.watzap.id/v1/send_message'
                && $body['phone_no'] === '628123456789'
                && ! isset($body['url']);
        });

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.watzap.id/v1/send_file_url');
    }

    public function test_auto_skips_pdf_on_http_local_app_url(): void
    {
        config([
            'app.url' => 'http://purchasing.test',
            'watzap.attach_pdf' => null,
        ]);

        $service = app(OrderWhatsappDeliveryService::class);
        $this->assertFalse($service->shouldAttachPdf());
    }

    public function test_auto_attaches_pdf_on_https_public_url(): void
    {
        config([
            'app.url' => 'https://purchasing.example.com',
            'watzap.attach_pdf' => null,
        ]);

        $service = app(OrderWhatsappDeliveryService::class);
        $this->assertTrue($service->shouldAttachPdf());
    }

    public function test_resend_whatsapp_accepts_watzap_delivered_response(): void
    {
        config(['watzap.attach_pdf' => false]);

        Http::fake([
            'https://api.watzap.id/v1/send_message' => Http::response([
                'status' => '200',
                'message' => 'The message is being delivered',
                'sender_number' => '6281284556165',
                'ack' => 'successfully',
            ]),
        ]);

        $order = $this->approvedOrder();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/resend-whatsapp")
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertNotNull($order->supplier_whatsapp_sent_at);
        $this->assertNull($order->supplier_whatsapp_error);
    }

    public function test_resend_whatsapp_rejects_watzap_file_server_error(): void
    {
        Http::fake([
            'https://api.watzap.id/v1/send_message' => Http::response([
                'status' => '200',
                'ack' => 'successfully',
            ]),
            'https://api.watzap.id/v1/send_file_url' => Http::response([
                'status' => '1005',
                'message' => 'Internal Server Error on File Server 500',
                'ack' => 'fatal_error',
            ]),
        ]);

        $order = $this->approvedOrder();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/resend-whatsapp")
            ->assertRedirect()
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertStringContainsString('Teks terkirim', $order->supplier_whatsapp_error ?? '');
        Storage::disk('local')->assertExists('watzap-delivery/'.$order->id.'/purchase-order-'.$order->order_number.'.pdf');
    }

    public function test_signed_pdf_delivery_endpoint_serves_pdf_for_approved_order(): void
    {
        $order = $this->approvedOrder();
        $filename = 'purchase-order-'.$order->order_number.'.pdf';

        $url = URL::temporarySignedRoute('orders.pdf.delivery', now()->addMinutes(10), [
            'order' => $order->id,
            'filename' => $filename,
        ]);

        $response = $this->get($url);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($filename, $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_unsigned_pdf_delivery_is_forbidden(): void
    {
        $order = $this->approvedOrder();

        $this->get("/delivery/orders/{$order->id}/pdf/purchase-order-{$order->order_number}.pdf")
            ->assertForbidden();
    }

    public function test_pdf_delivery_returns_not_found_for_pending_order(): void
    {
        $order = $this->approvedOrder();
        $filename = 'purchase-order-'.$order->order_number.'.pdf';
        $order->update(['status' => OrderStatus::Pending]);

        $url = URL::temporarySignedRoute('orders.pdf.delivery', now()->addMinutes(10), [
            'order' => $order->id,
            'filename' => $filename,
        ]);

        $this->get($url)->assertNotFound();
    }
}
