<?php

namespace App\Observers;

use App\Jobs\GeocodeListingJob;
use App\Models\Listing;

class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->location_source === 'manual') {
            if ($this->coordsPresent($listing) && !$listing->location_overridden_at) {
                $listing->forceFill(['location_overridden_at' => now()])->saveQuietly();
            }
            if ($this->coordsInvalid($listing)) {
                $listing->forceFill(['lat' => null, 'lng' => null, 'geocoded_at' => null])->saveQuietly();
            }
            return;
        }

        if ($this->coordsPresent($listing) && !$this->coordsInvalid($listing)) {
            if (!$listing->geocoded_at) {
                $listing->forceFill(['geocoded_at' => now()])->saveQuietly();
            }
            return;
        }

        GeocodeListingJob::dispatchSync($listing->id, true);
    }

    public function updated(Listing $listing): void
    {
        if ($listing->location_source === 'manual') {
            if ($this->coordsInvalid($listing)) {
                $listing->forceFill(['lat' => null, 'lng' => null])->saveQuietly();
            }
            return;
        }

        $addressChanged = $listing->wasChanged(['address', 'city', 'country']);
        $latLngProvided = $listing->wasChanged(['lat', 'lng']) && $listing->lat !== null && $listing->lng !== null;
        $latMissing = $listing->lat === null || $listing->lng === null || $this->coordsInvalid($listing);

        if ($latMissing || ($addressChanged && !$latLngProvided)) {
            GeocodeListingJob::dispatchSync($listing->id, $addressChanged);
            return;
        }

        if ($latLngProvided && !$listing->geocoded_at) {
            $listing->forceFill(['geocoded_at' => now()])->saveQuietly();
        }
    }

    private function coordsPresent(Listing $listing): bool
    {
        return $listing->lat !== null && $listing->lng !== null;
    }

    private function coordsInvalid(Listing $listing): bool
    {
        if ($listing->lat === null || $listing->lng === null) {
            return false;
        }

        return $listing->lat < -90 || $listing->lat > 90 || $listing->lng < -180 || $listing->lng > 180;
    }
}
