<?php

namespace App\Support;

class SpreadsheetId
{
    public static function from(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9\-_]+)#', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^[a-zA-Z0-9\-_]{20,}$#', $value)) {
            return $value;
        }

        return null;
    }
}
