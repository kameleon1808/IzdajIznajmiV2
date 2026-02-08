<?php

namespace App\Services;

class SavedSearchNormalizer
{
    public function normalize(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }

            if (in_array($key, ['instantBook', 'mapMode'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value !== true) {
                    continue;
                }
            }

            if (in_array($key, ['category', 'status'], true) && $value === 'all') {
                continue;
            }

            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($item) => $item !== null && $item !== '' && $item !== false));
                $value = array_map(fn ($item) => is_string($item) ? trim($item) : $item, $value);
                $value = array_values(array_unique($value, SORT_REGULAR));
                sort($value, SORT_NATURAL | SORT_FLAG_CASE);
                if (empty($value)) {
                    continue;
                }
            }

            if (in_array($key, ['priceMin', 'priceMax', 'rooms', 'areaMin', 'areaMax', 'guests'], true)) {
                if (! is_numeric($value)) {
                    continue;
                }
                $value = (int) $value;
            }

            if ($key === 'rating') {
                if (! is_numeric($value)) {
                    continue;
                }
                $value = (float) $value;
            }

            if ($key === 'centerLat') {
                if (! is_numeric($value)) {
                    continue;
                }
                $value = round((float) $value, 5);
            }

            if ($key === 'centerLng') {
                if (! is_numeric($value)) {
                    continue;
                }
                $value = round((float) $value, 5);
            }

            if ($key === 'radiusKm') {
                if (! is_numeric($value)) {
                    continue;
                }
                $value = round((float) $value, 2);
            }

            $normalized[$key] = $value;
        }

        if (! isset($normalized['amenities']) && isset($normalized['facilities'])) {
            $normalized['amenities'] = $normalized['facilities'];
            unset($normalized['facilities']);
        } elseif (isset($normalized['amenities']) && isset($normalized['facilities'])) {
            $merged = array_values(array_unique(array_merge($normalized['amenities'], $normalized['facilities']), SORT_REGULAR));
            sort($merged, SORT_NATURAL | SORT_FLAG_CASE);
            $normalized['amenities'] = $merged;
            unset($normalized['facilities']);
        }

        ksort($normalized);

        return $normalized;
    }
}
