<?php

namespace App\Services;

class WhatsappLinkService
{
    public function generate(string $phone, string $message): string
    {
        $normalizedPhone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($normalizedPhone, '0')) {
            $normalizedPhone = '62'.substr($normalizedPhone, 1);
        }

        return 'https://wa.me/'.$normalizedPhone.'?text='.rawurlencode($message);
    }
}
