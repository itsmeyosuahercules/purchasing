<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::defaults() as $key => $value) {
            Setting::set($key, $value);
        }

        Setting::set('company_name', 'PT Demo Perusahaan');
        Setting::set('company_email', 'Office@globsrc.com');
        Setting::set('whatsapp_contact', '089601811756');
        Setting::set('admin_email', 'admin@example.com');

        User::query()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Karyawan Demo',
            'username' => 'karyawan',
            'email' => null,
            'password' => 'password',
            'role' => UserRole::Employee,
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'real_name' => 'PT Supplier Asli',
            'alias_name' => 'Supplier A',
            'contact_person' => 'Budi Santoso',
            'email' => 'supplier@example.com',
            'whatsapp' => '6281234567890',
            'is_active' => true,
        ]);

        Product::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Beras Premium',
            'item_content' => 'botol + tutup + sedotan + buku petunjuk',
            'native_supplier_pn' => 'BR-50-PREM',
            'brand' => 'Cap Lele',
            'description' => 'Premium white rice 50kg packaging',
            'price' => 12000,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        Product::query()->create([
            'supplier_id' => $supplier->id,
            'name' => 'Minyak Goreng',
            'item_content' => '2L bottle',
            'native_supplier_pn' => 'MG-2L',
            'brand' => 'Bimoli',
            'description' => 'Cooking oil 2 liter bottle',
            'price' => 18000,
            'unit' => 'pcs',
            'is_active' => true,
        ]);
    }
}
