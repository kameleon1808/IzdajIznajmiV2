<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Http;

class NominatimSuggestGeocoder implements SuggestGeocoder
{
    private static float $lastRequestMs = 0.0;

    public function __construct(
        private readonly string $endpoint,
        private readonly ?string $email = null,
        private readonly ?string $countryCodes = null,
        private readonly int $rateLimitMs = 1200,
        private readonly int $timeoutSeconds = 8,
    ) {}

    public function suggest(string $query, int $limit = 5): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $this->respectRateLimit();

        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
            ])
            ->get($this->endpoint, array_filter([
                'q' => $q,
                'format' => 'json',
                'limit' => max(1, $limit),
                'addressdetails' => 0,
                'countrycodes' => $this->countryCodes,
                'email' => $this->email,
            ]));

        if (! $response->successful()) {
            return [];
        }

        $items = $response->json() ?? [];

        return collect($items)
            ->take($limit)
            ->map(function ($item) {
                return [
                    'label' => $item['display_name'] ?? 'Unknown',
                    'lat' => round((float) ($item['lat'] ?? 0), 7),
                    'lng' => round((float) ($item['lon'] ?? 0), 7),
                    'type' => $item['type'] ?? 'unknown',
                ];
            })
            ->filter(fn ($r) => $r['lat'] && $r['lng'])
            ->values()
            ->all();
    }

    private function userAgent(): string
    {
        $email = $this->email ?? 'noreply@example.com';

        return 'IzdajIznajmiSuggest/1.0 ('.$email.')';
    }

    private function respectRateLimit(): void
    {
        if ($this->rateLimitMs <= 0) {
            return;
        }

        $nowMs = microtime(true) * 1000;
        $elapsed = $nowMs - self::$lastRequestMs;
        if (self::$lastRequestMs > 0 && $elapsed < $this->rateLimitMs) {
            usleep((int) (($this->rateLimitMs - $elapsed) * 1000));
        }
        self::$lastRequestMs = microtime(true) * 1000;
    }
}
