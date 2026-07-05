<?php

namespace App\Services;

use App\Support\WhatsappNumber;

class WhatsappLinkService
{
    public function generate(string $phone, string $message): string
    {
        return 'https://wa.me/'.WhatsappNumber::normalize($phone).'?text='.rawurlencode($message);
    }
}
