<?php

namespace App\Support;

final class ListingAmenityNormalizer
{
    /**
     * @var array<string, string>
     */
    private const CANONICAL = [
        'basement' => 'Basement',
        'garage' => 'Garage',
        'terrace' => 'Terrace',
        'yard' => 'Yard',
        'internet' => 'Internet',
        'cable tv' => 'Cable TV',
        'phone' => 'Phone',
        'air conditioning' => 'Air conditioning',
        'elevator' => 'Elevator',
    ];

    /**
     * @var array<string, string>
     */
    private const ALIAS_TO_CANONICAL = [
        'basement' => 'Basement',
        'podrum' => 'Basement',

        'garage' => 'Garage',
        'garaza' => 'Garage',
        'parking' => 'Garage',

        'terrace' => 'Terrace',
        'terasa' => 'Terrace',

        'yard' => 'Yard',
        'dvoriste' => 'Yard',
        'garden' => 'Yard',

        'internet' => 'Internet',
        'wifi' => 'Internet',
        'wi-fi' => 'Internet',
        'wi fi' => 'Internet',

        'cable tv' => 'Cable TV',
        'cable' => 'Cable TV',
        'kablovska' => 'Cable TV',

        'phone' => 'Phone',
        'telefon' => 'Phone',

        'air conditioning' => 'Air conditioning',
        'air-conditioning' => 'Air conditioning',
        'ac' => 'Air conditioning',
        'klima' => 'Air conditioning',

        'elevator' => 'Elevator',
        'lift' => 'Elevator',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CANONICAL_VARIANTS = [
        'Basement' => ['Basement', 'Podrum'],
        'Garage' => ['Garage', 'Garaza', 'Garaža', 'Parking'],
        'Terrace' => ['Terrace', 'Terasa'],
        'Yard' => ['Yard', 'Dvoriste', 'Dvorište', 'Garden'],
        'Internet' => ['Internet', 'Wi-Fi', 'Wi Fi', 'Wifi'],
        'Cable TV' => ['Cable TV', 'Cable', 'Kablovska'],
        'Phone' => ['Phone', 'Telefon'],
        'Air conditioning' => ['Air conditioning', 'Air-conditioning', 'AC', 'Klima'],
        'Elevator' => ['Elevator', 'Lift'],
    ];

    public static function canonicalize(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = self::normalizeToken($trimmed);

        return self::ALIAS_TO_CANONICAL[$normalized] ?? $trimmed;
    }

    /**
     * @param  array<int, string>|string|null  $values
     * @return array<int, string>
     */
    public static function canonicalizeMany(array|string|null $values): array
    {
        if ($values === null) {
            return [];
        }

        $input = is_array($values) ? $values : explode(',', $values);

        $seen = [];
        $normalized = [];

        foreach ($input as $value) {
            if (! is_string($value)) {
                continue;
            }
            $canonical = self::canonicalize($value);
            if ($canonical === null) {
                continue;
            }
            $key = self::normalizeToken($canonical);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = $canonical;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function filterVariants(string $value): array
    {
        $canonical = self::canonicalize($value);
        if ($canonical === null) {
            return [];
        }

        $variants = self::CANONICAL_VARIANTS[$canonical] ?? [$canonical];

        // Preserve unique values while keeping stable order.
        $seen = [];
        $result = [];
        foreach ($variants as $variant) {
            $variant = trim((string) $variant);
            if ($variant === '') {
                continue;
            }
            $key = mb_strtolower($variant);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $variant;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public static function normalizedFilterVariants(string $value): array
    {
        $variants = self::filterVariants($value);
        $seen = [];
        $result = [];

        foreach ($variants as $variant) {
            $normalized = self::normalizeToken($variant);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $result[] = $normalized;
        }

        return $result;
    }

    /**
     * @param  array<int, array{value: string, count: int}>  $items
     * @return array<int, array{value: string, count: int}>
     */
    public static function canonicalizeFacetItems(array $items): array
    {
        $counts = [];
        $fallback = [];

        foreach ($items as $item) {
            $value = trim((string) ($item['value'] ?? ''));
            $count = (int) ($item['count'] ?? 0);
            if ($value === '' || $count <= 0) {
                continue;
            }

            $canonical = self::canonicalize($value);
            if ($canonical === null) {
                continue;
            }

            $key = self::normalizeToken($canonical);
            if (isset(self::CANONICAL[$key])) {
                $counts[$canonical] = ($counts[$canonical] ?? 0) + $count;
            } else {
                $fallback[$canonical] = ($fallback[$canonical] ?? 0) + $count;
            }
        }

        $ordered = [];
        foreach (self::CANONICAL as $canonical) {
            if (isset($counts[$canonical])) {
                $ordered[] = ['value' => $canonical, 'count' => $counts[$canonical]];
            }
        }

        if ($fallback !== []) {
            arsort($fallback);
            foreach ($fallback as $value => $count) {
                $ordered[] = ['value' => $value, 'count' => $count];
            }
        }

        return $ordered;
    }

    public static function normalizeToken(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return strtr($normalized, [
            'ć' => 'c',
            'č' => 'c',
            'ž' => 'z',
            'š' => 's',
            'đ' => 'dj',
        ]);
    }
}
