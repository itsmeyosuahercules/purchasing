<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'wechat_contact' => ['nullable', 'string', 'max:255'],
            'whatsapp_contact' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'ship_to' => ['nullable', 'string', 'max:500'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'shipping_method' => ['nullable', 'string', 'max:255'],
            'incoterms' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
            'po_validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'default_delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'terms_conditions' => ['nullable', 'string', 'max:10000'],
            'default_email_template' => ['required', 'string', 'max:5000'],
            'default_whatsapp_template' => ['required', 'string', 'max:5000'],
            'owner_whatsapp_template' => ['required', 'string', 'max:5000'],
        ];
    }
}
