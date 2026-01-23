<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Geocoding\Geocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeocodeListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $listingId, public bool $forceRefresh = false)
    {
    }

    public function handle(Geocoder $geocoder, CacheRepository $cache): void
    {
        $listing = Listing::find($this->listingId);
        if (!$listing) {
            return;
        }

        if ($listing->location_source === 'manual') {
            return;
        }

        $latPresent = $listing->lat !== null && $listing->lng !== null;
        $latInvalid = $latPresent && ($listing->lat < -90 || $listing->lat > 90 || $listing->lng < -180 || $listing->lng > 180);
        if ($latInvalid) {
            $listing->forceFill(['lat' => null, 'lng' => null, 'geocoded_at' => null])->saveQuietly();
            $latPresent = false;
        }

        if (!$this->forceRefresh && $latPresent && $listing->geocoded_at) {
            return;
        }

        if ($latPresent && !$this->forceRefresh && !$listing->geocoded_at) {
            $listing->forceFill(['geocoded_at' => now()])->saveQuietly();
            return;
        }

        $parts = array_filter([$listing->address, $listing->city, $listing->country], fn ($part) => filled($part));
        if (empty($parts)) {
            return;
        }

        $address = implode(', ', $parts);
        $ttlMinutes = (int) config('geocoding.cache_ttl_minutes', 1440);
        $cacheKey = 'geocode:'.sha1(mb_strtolower(trim($address)));

        $result = $cache->remember(
            $cacheKey,
            now()->addMinutes($ttlMinutes),
            fn () => $geocoder->geocode($address)
        );

        if (!$result || !isset($result['lat'], $result['lng'])) {
            $listing->forceFill(['geocoded_at' => null])->saveQuietly();
            return;
        }

        $listing->forceFill([
            'lat' => $result['lat'],
            'lng' => $result['lng'],
            'geocoded_at' => now(),
            'location_source' => 'geocoded',
            'location_overridden_at' => null,
        ])->saveQuietly();
    }
}
