<?php

namespace App\Services\Geocoding;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class CachedSuggestGeocoder implements SuggestGeocoder
{
    public function __construct(
        private readonly SuggestGeocoder $inner,
        private readonly CacheRepository $cache,
        private readonly int $ttlMinutes = 15
    ) {}

    public function suggest(string $query, int $limit = 5): array
    {
        $normalized = Str::of($query)->lower()->trim()->toString();
        if ($normalized === '') {
            return [];
        }

        $cacheKey = 'geocode:suggest:'.sha1($normalized.':'.$limit);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $fresh = $this->inner->suggest($normalized, $limit);
        if (! empty($fresh)) {
            $this->cache->put($cacheKey, $fresh, now()->addMinutes($this->ttlMinutes));
        }

        return $fresh;
    }
}
