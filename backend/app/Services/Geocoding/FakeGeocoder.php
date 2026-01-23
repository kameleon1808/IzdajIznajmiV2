<?php

namespace App\Services\Geocoding;

class FakeGeocoder implements Geocoder
{
    public function __construct(
        private readonly float $baseLat = 45.0,
        private readonly float $baseLng = 15.0,
        private readonly float $spreadKm = 120.0
    ) {
    }

    public function geocode(string $address): ?array
    {
        $normalized = trim(mb_strtolower($address));
        if ($normalized === '') {
            return null;
        }

        $latOffset = $this->offsetValue($normalized.'lat');
        $lngOffset = $this->offsetValue($normalized.'lng');

        $kmPerDegreeLat = 111.045;
        $safeBaseLat = max(min($this->baseLat, 89.0), -89.0);
        $kmPerDegreeLng = $kmPerDegreeLat * max(cos(deg2rad($safeBaseLat)), 0.1);

        $lat = $this->baseLat + ($latOffset * ($this->spreadKm / $kmPerDegreeLat));
        $lng = $this->baseLng + ($lngOffset * ($this->spreadKm / $kmPerDegreeLng));

        return [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
        ];
    }

    private function offsetValue(string $seed): float
    {
        $hash = hexdec(substr(hash('sha256', $seed), 0, 12));
        // Map 48-bit hash to range [-1, 1]
        return ($hash / 0xFFFFFFFFFFFF) * 2 - 1;
    }
}
