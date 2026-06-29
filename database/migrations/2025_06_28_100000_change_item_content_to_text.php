<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'item_content')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY item_content TEXT NULL');
            DB::statement('ALTER TABLE order_items MODIFY item_content TEXT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite tidak bisa MODIFY kolom dengan mudah; fresh install pakai migration asli (text).
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'item_content')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY item_content VARCHAR(255) NULL');
            DB::statement('ALTER TABLE order_items MODIFY item_content VARCHAR(255) NULL');
        }
    }
};
