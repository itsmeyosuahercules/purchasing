<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('supplier_whatsapp_sent_at')->nullable()->after('supplier_emailed_at');
            $table->string('supplier_whatsapp_error', 1000)->nullable()->after('supplier_whatsapp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_whatsapp_sent_at', 'supplier_whatsapp_error']);
        });
    }
};
