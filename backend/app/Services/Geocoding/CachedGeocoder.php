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

        return $this->cache->remember(
            $cacheKey,
            now()->addMinutes($this->ttlMinutes),
            fn () => $this->inner->geocode($normalized)
        );
    }
}
