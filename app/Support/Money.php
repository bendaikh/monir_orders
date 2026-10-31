<?php

namespace App\Support;

class Money
{
    public static function format(mixed $amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $formatted = number_format((float) $amount, 2, ',', ' ');

        return trim($formatted.' '.($currency ?? ''));
    }

    public static function parse(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(["\xc2\xa0", ' '], '', trim((string) $value));

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\d,.\-]/', '', $value) ?? '';

        if ($value === '' || $value === '-') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
