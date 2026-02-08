<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Http;

class NominatimGeocoder implements Geocoder
{
    private static float $lastRequestMs = 0.0;

    public function __construct(
        private readonly string $endpoint,
        private readonly ?string $email = null,
        private readonly ?string $countryCodes = null,
        private readonly int $rateLimitMs = 1200,
        private readonly int $timeoutSeconds = 8,
    ) {}

    public function geocode(string $address): ?array
    {
        $query = trim($address);
        if ($query === '') {
            return null;
        }

        $this->respectRateLimit();

        $response = Http::timeout($this->timeoutSeconds)
            ->withHeaders([
                'User-Agent' => $this->userAgent(),
            ])
            ->get($this->endpoint, array_filter([
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0,
                'countrycodes' => $this->countryCodes,
                'email' => $this->email,
            ]));

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json()[0] ?? null;
        if (! $data || ! isset($data['lat'], $data['lon'])) {
            return null;
        }

        return [
            'lat' => round((float) $data['lat'], 7),
            'lng' => round((float) $data['lon'], 7),
        ];
    }

    private function userAgent(): string
    {
        $email = $this->email ?? 'noreply@example.com';

        return 'IzdajIznajmiGeocoder/1.0 ('.$email.')';
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
