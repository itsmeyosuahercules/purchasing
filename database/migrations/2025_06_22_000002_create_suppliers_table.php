<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('real_name');
            $table->string('alias_name');
            $table->string('email');
            $table->string('whatsapp', 30);
            $table->text('email_template')->nullable();
            $table->text('whatsapp_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('alias_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
