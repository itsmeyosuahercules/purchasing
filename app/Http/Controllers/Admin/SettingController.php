<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;

class SettingController extends Controller
{
    public function edit()
    {
        $keys = [
            'company_name', 'company_email', 'wechat_contact', 'whatsapp_contact', 'admin_email',
            'ship_to', 'payment_terms', 'shipping_method', 'incoterms', 'currency',
            'po_validity_days', 'default_delivery_days', 'terms_conditions',
            'default_email_template', 'default_whatsapp_template',
        ];

        $settings = collect($keys)->mapWithKeys(
            fn (string $key) => [$key => Setting::get($key, Setting::defaults()[$key] ?? '')]
        )->all();

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(SettingRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
