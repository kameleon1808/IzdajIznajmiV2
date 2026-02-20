<?php

namespace App\Support;

final class MediaUrl
{
    public static function publicStorage(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    public static function normalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $value = trim($url);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            return '/'.ltrim($value, '/');
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return $value;
        }

        $query = parse_url($value, PHP_URL_QUERY);
        $fragment = parse_url($value, PHP_URL_FRAGMENT);

        $normalized = $path;
        if (is_string($query) && $query !== '') {
            $normalized .= '?'.$query;
        }
        if (is_string($fragment) && $fragment !== '') {
            $normalized .= '#'.$fragment;
        }

        return $normalized;
    }
}
