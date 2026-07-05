<?php

namespace App\Support;

class QuantityFormatter
{
    /**
     * Format qty tanpa desimal sia-sia: 2 → "2", 2.5 → "2,5" (locale ID).
     */
    public static function format(float|string|null $quantity, string $decimalSeparator = ','): string
    {
        if ($quantity === null || $quantity === '') {
            return '0';
        }

        $qty = (float) $quantity;

        if (abs($qty - round($qty)) < 0.00001) {
            return (string) (int) round($qty);
        }

        $formatted = number_format($qty, 2, $decimalSeparator, '');

        return rtrim(rtrim($formatted, '0'), $decimalSeparator);
    }
}
