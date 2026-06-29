<?php

namespace App\Support;

class RupiahTerbilang
{
    private const UNITS = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
        'sepuluh', 'sebelas', 'dua belas', 'tiga belas', 'empat belas', 'lima belas',
        'enam belas', 'tujuh belas', 'delapan belas', 'sembilan belas',
    ];

    public static function format(float|int $amount): string
    {
        $whole = (int) round($amount);

        if ($whole === 0) {
            return 'nol';
        }

        return trim(self::convert($whole));
    }

    private static function convert(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            $tens = (int) floor($number / 10);
            $rest = $number % 10;

            return trim(self::UNITS[$tens].' puluh '.($rest ? self::UNITS[$rest] : ''));
        }

        if ($number < 200) {
            return 'seratus '.self::convert($number - 100);
        }

        if ($number < 1000) {
            $hundreds = (int) floor($number / 100);

            return trim(self::UNITS[$hundreds].' ratus '.self::convert($number % 100));
        }

        if ($number < 2000) {
            return 'seribu '.self::convert($number - 1000);
        }

        if ($number < 1_000_000) {
            $thousands = (int) floor($number / 1000);

            return trim(self::convert($thousands).' ribu '.self::convert($number % 1000));
        }

        if ($number < 1_000_000_000) {
            $millions = (int) floor($number / 1_000_000);

            return trim(self::convert($millions).' juta '.self::convert($number % 1_000_000));
        }

        $billions = (int) floor($number / 1_000_000_000);

        return trim(self::convert($billions).' miliar '.self::convert($number % 1_000_000_000));
    }
}
