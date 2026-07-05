<?php

namespace App\Http\Requests;

use App\Support\WhatsappNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('whatsapp')) {
            $this->merge([
                'whatsapp' => WhatsappNumber::normalize($this->string('whatsapp')->toString()),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'real_name' => ['required', 'string', 'max:255'],
            'alias_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20', 'regex:/^62\d{8,15}$/'],
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
            'whatsapp.regex' => 'Nomor WhatsApp untuk WatZap wajib diawali 62, contoh: 628123456789.',
        ];
    }
}
