<?php

namespace App\Services\Api;

use App\Models\PartnerService;

class CityMatcher
{
    public static function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Explicit mapping first, then fuzzy match on the city and the address, then the fallback city.
     */
    public function resolve(PartnerService $service, ?string $city, ?string $address = null): ?string
    {
        foreach ([$city, $address] as $text) {
            $key = self::normalize($text);

            if ($key === '') {
                continue;
            }

            $mapped = $service->cityMappings->first(fn ($m) => self::normalize($m->source_city) === $key);

            if ($mapped) {
                return $mapped->city;
            }
        }

        foreach ([$city, $address] as $text) {
            if ($match = $this->fuzzy($text)) {
                return $match;
            }
        }

        return $service->api_city_fallback ?: null;
    }

    public function fuzzy(?string $text): ?string
    {
        $text = self::normalize($text);

        if ($text === '') {
            return null;
        }

        $cities = config('simon_express.cities', []);
        $aliases = ['dar' => 'DAR SALAM', 'dar es salaam' => 'DAR SALAM', 'dar es salam' => 'DAR SALAM', 'dsm' => 'DAR SALAM', 'zanzibar' => 'ZANZIBAR', 'uyole' => 'mbeya-uyole', 'songwe' => 'mbeya-songwe'];
        $words = explode(' ', $text);

        foreach ($aliases as $alias => $city) {
            if (str_contains(' '.$text.' ', ' '.$alias.' ')) {
                return $city;
            }
        }

        // Whole-word match, longest city name first (so "mbeya uyole" beats "mbeya").
        $sorted = $cities;
        usort($sorted, fn ($a, $b) => strlen(self::normalize($b)) <=> strlen(self::normalize($a)));

        foreach ($sorted as $city) {
            $name = self::normalize($city);

            if ($name !== '' && str_contains(' '.$text.' ', ' '.$name.' ')) {
                return $city;
            }
        }

        // Typo tolerance on single words (e.g. "Arushaa", "Mwanzza").
        foreach ($words as $word) {
            if (strlen($word) < 4) {
                continue;
            }

            foreach ($cities as $city) {
                $name = self::normalize($city);

                if (! str_contains($name, ' ') && levenshtein($word, $name) <= (strlen($name) >= 7 ? 2 : 1)) {
                    return $city;
                }
            }
        }

        return null;
    }
}
