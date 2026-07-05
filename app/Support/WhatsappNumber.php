<?php

namespace App\Support;

class WhatsappNumber
{
    /**
     * Normalisasi nomor untuk WatZap API — selalu diawali 62.
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    public static function isValidForWatzap(string $phone): bool
    {
        $normalized = self::normalize($phone);

        return (bool) preg_match('/^62\d{8,15}$/', $normalized);
    }
}
