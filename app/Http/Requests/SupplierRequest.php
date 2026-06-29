<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'real_name' => ['required', 'string', 'max:255'],
            'alias_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'email_template' => ['nullable', 'string', 'max:5000'],
            'whatsapp_template' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }

    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, +, -, atau spasi.',
        ];
    }
}
