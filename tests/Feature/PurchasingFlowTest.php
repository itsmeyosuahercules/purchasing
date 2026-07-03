<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\OrderToSupplierMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchasingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.com',
            'password' => 'password', 'role' => UserRole::Admin, 'is_active' => true,
        ]);
    }

    private function employee(): User
    {
        return User::create([
            'name' => 'Karyawan', 'username' => 'karyawan', 'email' => null,
            'password' => 'password', 'role' => UserRole::Employee, 'is_active' => true,
        ]);
    }

    private function supplierWithProduct(): Supplier
    {
        $supplier = Supplier::create([
            'real_name' => 'PT Rahasia', 'alias_name' => 'Supplier X',
            'email' => 'sup@test.com', 'whatsapp' => '628123456789', 'is_active' => true,
        ]);
        $supplier->products()->create([
            'name' => 'Beras', 'price' => 12345, 'unit' => 'kg', 'is_active' => true,
        ]);

        return $supplier;
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Nonaktif', 'username' => 'nonaktif', 'password' => 'password',
            'role' => UserRole::Employee, 'is_active' => false,
        ]);

        $this->post('/login', ['username' => 'nonaktif', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_employee_cannot_access_admin(): void
    {
        $this->actingAs($this->employee())->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_load(): void
    {
        $admin = $this->admin();
        $this->supplierWithProduct();

        foreach (['/admin', '/admin/orders', '/admin/suppliers', '/admin/products', '/admin/users', '/admin/settings'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_employee_pages_load(): void
    {
        $employee = $this->employee();
        $this->actingAs($employee)->get('/employee/orders/create')->assertOk();
        $this->actingAs($employee)->get('/employee/orders/history')->assertOk();
    }

    public function test_full_order_and_approval_flow(): void
    {
        Mail::fake();

        $employee = $this->employee();
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $this->actingAs($employee)->post('/employee/orders', [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertEquals(12345, $order->items->first()->price);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/approve")->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Approved, $order->status);
        $this->assertSame($admin->id, $order->approved_by);
        $this->assertNotNull($order->whatsapp_link);
        $this->assertStringStartsWith('PO-', $order->order_number);
        $this->assertNotNull($order->valid_until);
        $this->assertNotNull($order->delivery_date);
        Mail::assertSent(OrderToSupplierMail::class, fn (OrderToSupplierMail $mail) => count($mail->attachments()) === 1);
        $this->assertNotNull($order->supplier_emailed_at);
    }

    public function test_admin_can_resend_email_for_approved_order(): void
    {
        Mail::fake();

        $employee = $this->employee();
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $order = Order::create([
            'order_number' => 'ORD-TEST-0002',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Beras',
            'quantity' => 3, 'unit' => 'kg', 'price' => 12345,
        ]);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-email")
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertNotNull($order->supplier_emailed_at);
        Mail::assertSent(OrderToSupplierMail::class);
    }

    public function test_cannot_resend_email_for_pending_order(): void
    {
        Mail::fake();

        $employee = $this->employee();
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();

        $order = Order::create([
            'order_number' => 'ORD-TEST-0003',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-email")
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_admin_can_resend_whatsapp_for_approved_order(): void
    {
        config([
            'watzap.enabled' => true,
            'watzap.api_key' => 'test-api-key',
            'watzap.number_key' => 'test-number-key',
            'watzap.attach_pdf' => false,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.watzap.id/v1/send_message' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'message' => 'success',
            ]),
        ]);

        $employee = $this->employee();
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $order = Order::create([
            'order_number' => 'ORD-TEST-WA-01',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Beras',
            'quantity' => 3, 'unit' => 'kg', 'price' => 12345,
        ]);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-whatsapp")
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertNotNull($order->supplier_whatsapp_sent_at);
        $this->assertNull($order->supplier_whatsapp_error);
    }

    public function test_cannot_resend_whatsapp_for_pending_order(): void
    {
        config([
            'watzap.enabled' => true,
            'watzap.api_key' => 'test-api-key',
            'watzap.number_key' => 'test-number-key',
        ]);

        \Illuminate\Support\Facades\Http::fake();

        $employee = $this->employee();
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();

        $order = Order::create([
            'order_number' => 'ORD-TEST-WA-02',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-whatsapp")
            ->assertRedirect()
            ->assertSessionHas('error');

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_employee_history_hides_price(): void
    {
        $employee = $this->employee();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $order = Order::create([
            'order_number' => 'ORD-TEST-0001',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Beras',
            'quantity' => 5, 'unit' => 'kg', 'price' => 12345,
        ]);

        $response = $this->actingAs($employee)->get("/employee/orders/{$order->id}");
        $response->assertOk();
        $response->assertDontSee('12.345');
        $response->assertDontSee('PT Rahasia');
        $response->assertSee('Supplier X');
    }

    public function test_admin_can_preview_and_download_pdf(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $order = Order::create([
            'order_number' => 'ORD-PDF-0001',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Beras',
            'quantity' => 5, 'unit' => 'kg', 'price' => 12345,
        ]);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}/pdf/preview")
            ->assertOk()->assertSee('Preview PDF');

        $inline = $this->actingAs($admin)->get("/admin/orders/{$order->id}/pdf/inline");
        $inline->assertOk();
        $this->assertStringContainsString('application/pdf', $inline->headers->get('Content-Type'));
        $this->assertStringContainsString('purchase-order-', $inline->headers->get('Content-Disposition') ?? '');

        $download = $this->actingAs($admin)->get("/admin/orders/{$order->id}/pdf/download");
        $download->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
    }

    public function test_employee_pdf_hides_price_and_supplier_real_name(): void
    {
        $employee = $this->employee();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $order = Order::create([
            'order_number' => 'ORD-PDF-0002',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Beras',
            'quantity' => 5, 'unit' => 'kg', 'price' => 12345,
        ]);

        $order->load(['supplier', 'items', 'user']);

        $html = view('pdf.order', [
            'order' => $order,
            'companyName' => 'PT Demo',
            'companyEmail' => null,
            'wechatContact' => null,
            'whatsappContact' => null,
            'shipTo' => 'Will be notified before delivery',
            'paymentTerms' => 'As Usual',
            'shippingMethod' => 'As Usual',
            'incoterms' => 'Exworks',
            'currency' => 'IDR',
            'termsConditions' => '',
            'amountInWords' => 'enam puluh satu ribu tujuh ratus dua puluh lima',
            'forEmployee' => true,
        ])->render();

        $this->assertStringContainsString('PURCHASE ORDER', $html);
        $this->assertStringContainsString('VENDOR / SUPPLIER', $html);
        $this->assertStringContainsString('Supplier X', $html);
        $this->assertStringNotContainsString('PT Rahasia', $html);
        $this->assertStringNotContainsString('12.345', $html);

        $response = $this->actingAs($employee)->get("/employee/orders/{$order->id}/pdf/inline");
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_admin_can_search_and_export_orders(): void
    {
        $employee = $this->employee();
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();
        $product = $supplier->products()->first();

        $order = Order::create([
            'order_number' => 'ORD-FIND-9999',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Beras',
            'quantity' => 2, 'unit' => 'kg', 'price' => 12345,
        ]);

        $this->actingAs($admin)->get('/admin/orders?search=FIND-9999')
            ->assertOk()->assertSee('ORD-FIND-9999');

        $this->actingAs($admin)->get('/admin/orders?search=TIDAKADA')
            ->assertOk()->assertDontSee('ORD-FIND-9999');

        $response = $this->actingAs($admin)->get('/admin/orders/export');
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_product_accepts_plain_integer_price(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();

        $this->actingAs($admin)->post('/admin/products', [
            'supplier_id' => $supplier->id,
            'name' => 'Gula',
            'price' => '15000',
            'unit' => 'kg',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Gula', 'price' => 15000]);
    }

    public function test_admin_can_update_po_details(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $supplier = $this->supplierWithProduct();

        $order = Order::create([
            'order_number' => 'PO-TEST-0001',
            'user_id' => $employee->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($admin)->put("/admin/orders/{$order->id}/po-details", [
            'reference_rfq_no' => 'RFQ-2025-001',
            'valid_until' => '2025-07-31',
            'delivery_date' => '2025-07-15',
            'notes' => 'Packaging must be labeled with PO number.',
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('RFQ-2025-001', $order->reference_rfq_no);
        $this->assertSame('2025-07-31', $order->valid_until->format('Y-m-d'));
        $this->assertSame('Packaging must be labeled with PO number.', $order->notes);
    }

    public function test_product_stores_po_item_fields(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplierWithProduct();

        $this->actingAs($admin)->post('/admin/products', [
            'supplier_id' => $supplier->id,
            'name' => 'Bolt M8',
            'item_content' => 'botol + tutup + sedotan + buku petunjuk + label + shrink wrap',
            'native_supplier_pn' => 'BLT-M8-SS',
            'brand' => 'Unbrako',
            'description' => 'Hex bolt M8 x 25mm',
            'price' => '2500',
            'unit' => 'pcs',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name' => 'Bolt M8',
            'native_supplier_pn' => 'BLT-M8-SS',
            'brand' => 'Unbrako',
        ]);
    }

    public function test_employee_cannot_view_others_order(): void
    {
        $owner = $this->employee();
        $other = User::create([
            'name' => 'Lain', 'username' => 'lain', 'password' => 'password',
            'role' => UserRole::Employee, 'is_active' => true,
        ]);
        $supplier = $this->supplierWithProduct();

        $order = Order::create([
            'order_number' => 'ORD-TEST-0002',
            'user_id' => $owner->id,
            'supplier_id' => $supplier->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($other)->get("/employee/orders/{$order->id}")->assertForbidden();
    }
}
