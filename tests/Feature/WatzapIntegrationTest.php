<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\SendOrderWhatsappJob;
use App\Mail\OrderToSupplierMail;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderTemplateService;
use App\Services\OrderWhatsappDeliveryService;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

    public function test_approve_sends_whatsapp_when_watzap_enabled(): void
    {
        Mail::fake();
        config(['watzap.attach_pdf' => false]);

        Setting::set('whatsapp_contact', '08999888777');

        Http::fake([
            'https://api.watzap.id/v1/send_message' => Http::response([
                'status' => true,
                'message' => 'success',
            ]),
        ]);

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

        $order->refresh();
        $this->assertNotNull($order->supplier_whatsapp_sent_at);
        $this->assertNotNull($order->supplier_emailed_at);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.watzap.id/v1/send_message') {
                return false;
            }

            return str_contains((string) ($request->data()['message'] ?? ''), 'Salinan Owner');
        });
        Mail::assertSent(OrderToSupplierMail::class);
    }

    public function test_whatsapp_template_formats_quantity_without_trailing_decimals(): void
    {
        $order = $this->approvedOrder();
        $order->items()->first()->update(['quantity' => 2, 'unit' => 'pcs']);

        $body = app(OrderTemplateService::class)->getWhatsappTemplate($order->fresh(['supplier', 'items']));

        $this->assertStringContainsString('2 pcs', $body);
        $this->assertStringNotContainsString('2.00', $body);
        $this->assertStringNotContainsString('2,00', $body);
    }

    public function test_resend_whatsapp_calls_watzap_api_with_pdf_url(): void
    {
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

        $order->refresh();
        $this->assertNotNull($order->supplier_whatsapp_sent_at);
        $this->assertNull($order->supplier_whatsapp_error);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.watzap.id/v1/send_message'
                && str_contains($body['message'], 'PT Supplier')
                && str_contains($body['message'], '/po/')
                && str_contains($body['message'], 'PO-WA-0001');
        });

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.watzap.id/v1/send_file_url');
    }

    public function test_combined_mode_sends_file_url_with_caption(): void
    {
        config(['watzap.send_mode' => 'combined']);

        Http::fake([
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

        Http::assertSent(function ($request) use ($order) {
            $body = $request->data();
            $filename = 'purchase-order-'.$order->order_number.'.pdf';

            return $request->url() === 'https://api.watzap.id/v1/send_file_url'
                && str_ends_with($body['url'], '/'.$filename)
                && str_contains($body['message'] ?? '', 'PT Supplier');
        });

        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.watzap.id/v1/send_message');
    }

    public function test_separate_mode_sends_text_then_pdf(): void
    {
        config(['watzap.send_mode' => 'separate']);

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

        Http::assertSent(fn ($request) => $request->url() === 'https://api.watzap.id/v1/send_message');
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.watzap.id/v1/send_file_url'
                && ! isset($body['message']);
        });
    }

    public function test_short_po_download_link_serves_pdf(): void
    {
        $order = $this->approvedOrder();
        $url = app(\App\Services\OrderPdfService::class)->createShortDownloadUrl($order);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $response = $this->get('/po/'.$token);
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('purchase-order-'.$order->order_number.'.pdf', $response->headers->get('Content-Disposition') ?? '');
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

    public function test_send_to_supplier_cleans_up_pdf_after_watzap_failure(): void
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

        try {
            app(OrderWhatsappDeliveryService::class)->sendToSupplier($order);
            $this->fail('Expected WatzapDeliveryException');
        } catch (\Throwable) {
            // expected
        }

        $this->assertEmpty(glob(public_path('watzap-delivery').'/*/*.pdf') ?: []);
    }

    public function test_whatsapp_job_skips_retry_when_text_already_sent(): void
    {
        Http::fake();

        $order = $this->approvedOrder();
        $order->forceFill([
            'supplier_whatsapp_sent_at' => now(),
            'supplier_whatsapp_error' => 'Teks terkirim, PDF gagal: contoh error',
        ])->save();

        (new SendOrderWhatsappJob($order->id))->handle(app(OrderWhatsappDeliveryService::class));

        Http::assertNothingSent();
    }

    public function test_resend_whatsapp_rejects_watzap_file_server_error(): void
    {
        config(['watzap.send_mode' => 'separate']);

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
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertStringContainsString('Teks terkirim', $order->supplier_whatsapp_error ?? '');
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('watzap-delivery').'/*/*.pdf') ?: [] as $pdf) {
            @unlink($pdf);
        }

        foreach (glob(public_path('watzap-delivery').'/*') ?: [] as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }

        parent::tearDown();
    }

    public function test_static_watzap_pdf_is_written_to_public_folder(): void
    {
        $order = $this->approvedOrder();
        $publication = app(\App\Services\OrderPdfService::class)->publishForWatzap($order);

        $this->assertFileExists($publication['path']);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($publication['path']));
        $this->assertSame('purchase-order-'.$order->order_number.'.pdf', $publication['filename']);
        $this->assertSame(
            rtrim((string) config('app.url'), '/').'/watzap-delivery/'.$publication['relative_path'],
            $publication['url'],
        );
        $this->assertStringEndsWith($publication['filename'], $publication['url']);

        app(\App\Services\OrderPdfService::class)->cleanupWatzapPublication($publication['relative_path']);
        $this->assertFileDoesNotExist($publication['path']);
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
