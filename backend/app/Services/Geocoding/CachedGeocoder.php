<?php

namespace App\Services\Geocoding;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class CachedGeocoder implements Geocoder
{
    public function __construct(
        private readonly Geocoder $inner,
        private readonly CacheRepository $cache,
        private readonly int $ttlMinutes = 1440
    ) {
    }

    public function geocode(string $address): ?array
    {
        $normalized = Str::of($address)->lower()->trim()->toString();
        if ($normalized === '') {
            return null;
        }

        $cacheKey = 'geocode:'.sha1($normalized);

        $cached = $this->cache->get($cacheKey);
        if ($this->isValid($cached)) {
            return $cached;
        }

        $fresh = $this->inner->geocode($normalized);
        if ($this->isValid($fresh)) {
            $this->cache->put($cacheKey, $fresh, now()->addMinutes($this->ttlMinutes));
            return $fresh;
        }

        $this->cache->forget($cacheKey);

        return $fresh;
    }

    private function isValid(mixed $result): bool
    {
        if (!is_array($result)) {
            return false;
        }
        if (!isset($result['lat'], $result['lng'])) {
            return false;
        }

        $lat = (float) $result['lat'];
        $lng = (float) $result['lng'];

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}
