<?php

namespace App\Support;

use App\Models\PartnerService;
use Carbon\Carbon;
use Throwable;

class OrderNormalizer
{
    public const DUPLICATE_MODES = [
        'reference' => 'Référence identique',
        'phone_product_day' => 'Même téléphone + même produit + même jour',
        'phone_product' => 'Même téléphone + même produit (toutes dates)',
        'phone_day' => 'Même téléphone + même jour',
        'none' => 'Aucune détection (déconseillé)',
    ];

    public const DECIMAL_FORMATS = [
        'auto' => 'Automatique',
        'point' => 'Point décimal (85.000 = 85 ; 1,250.5 = 1250,5)',
        'comma' => 'Virgule décimale (85,5 = 85,5 ; 1.250,5 = 1250,5)',
    ];

    public const DATE_FALLBACKS = [
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'm/d/Y H:i:s', 'm/d/Y',
    ];

    public const PLACEHOLDER_PREFIX = '(non mapp';

    public static function isPlaceholder(?string $column): bool
    {
        $column = trim((string) $column);

        return $column === '' || str_starts_with(mb_strtolower($column), self::PLACEHOLDER_PREFIX);
    }

    /**
     * Digits only, without international prefixes, keeping the last N significant digits.
     */
    public static function phone(mixed $value, int $digits = 8): ?string
    {
        $clean = preg_replace('/\D+/', '', (string) $value) ?? '';
        $clean = ltrim($clean, '0');

        if ($clean === '') {
            return null;
        }

        $digits = max(6, min(15, $digits));

        return strlen($clean) > $digits ? substr($clean, -$digits) : $clean;
    }

    public static function date(mixed $value, ?string $format = null): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = array_values(array_unique(array_filter([$format, ...self::DATE_FALLBACKS])));

        foreach ($formats as $candidate) {
            try {
                $date = Carbon::createFromFormat('!'.$candidate, $value);
            } catch (Throwable) {
                continue;
            }

            $errors = Carbon::getLastErrors();

            if ($date !== false && $date !== null && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public static function money(mixed $value, ?string $format = 'auto'): ?string
    {
        if ($format === null || $format === 'auto') {
            return Money::parse($value);
        }

        $value = str_replace(["\xc2\xa0", ' '], '', trim((string) $value));
        $value = preg_replace('/[^\d,.\-]/', '', $value) ?? '';

        if ($value === '' || $value === '-') {
            return null;
        }

        $value = $format === 'comma'
            ? str_replace(',', '.', str_replace('.', '', $value))
            : str_replace(',', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function product(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $value))) ?? '';
    }

    public static function duplicateKey(PartnerService $service, ?string $reference, mixed $phone, mixed $product, ?Carbon $orderedAt): ?string
    {
        $mode = $service->duplicateMode();
        $phone = self::phone($phone, (int) ($service->phone_digits ?: 8));
        $product = self::product($product);
        $day = $orderedAt?->format('Y-m-d');

        return match ($mode) {
            'reference' => filled($reference) ? 'ref:'.mb_strtolower(trim((string) $reference)) : null,
            'phone_product_day' => $phone && $product !== '' && $day ? 'ppd:'.$phone.'|'.$product.'|'.$day : null,
            'phone_product' => $phone && $product !== '' ? 'pp:'.$phone.'|'.$product : null,
            'phone_day' => $phone && $day ? 'pd:'.$phone.'|'.$day : null,
            default => null,
        };
    }

    public static function isExcluded(PartnerService $service, mixed $product): bool
    {
        $terms = array_filter(array_map(
            fn ($term) => self::product($term),
            explode(',', (string) $service->exclude_products),
        ));

        if ($terms === []) {
            return false;
        }

        $product = self::product($product);

        foreach ($terms as $term) {
            if ($term !== '' && str_contains($product, $term)) {
                return true;
            }
        }

        return false;
    }
}
