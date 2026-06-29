<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('alias_name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('item_content')->nullable()->after('name');
            $table->string('native_supplier_pn')->nullable()->after('item_content');
            $table->string('brand')->nullable()->after('native_supplier_pn');
            $table->text('description')->nullable()->after('brand');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('reference_rfq_no')->nullable()->after('order_number');
            $table->date('valid_until')->nullable()->after('approved_at');
            $table->date('delivery_date')->nullable()->after('valid_until');
            $table->text('notes')->nullable()->after('rejection_reason');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->text('item_content')->nullable()->after('product_name');
            $table->string('native_supplier_pn')->nullable()->after('item_content');
            $table->string('brand')->nullable()->after('native_supplier_pn');
            $table->text('description')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('contact_person');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['item_content', 'native_supplier_pn', 'brand', 'description']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reference_rfq_no', 'valid_until', 'delivery_date', 'notes']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['item_content', 'native_supplier_pn', 'brand', 'description']);
        });
    }
};
