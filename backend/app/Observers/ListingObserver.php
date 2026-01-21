<?php

namespace App\Observers;

use App\Jobs\GeocodeListingJob;
use App\Models\Listing;

class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->lat !== null && $listing->lng !== null) {
            if (!$listing->geocoded_at) {
                $listing->forceFill(['geocoded_at' => now()])->saveQuietly();
            }
            return;
        }

        GeocodeListingJob::dispatchSync($listing->id, true);
    }

    public function updated(Listing $listing): void
    {
        $addressChanged = $listing->wasChanged(['address', 'city', 'country']);
        $latLngProvided = $listing->wasChanged(['lat', 'lng']) && $listing->lat !== null && $listing->lng !== null;
        $latMissing = $listing->lat === null || $listing->lng === null;

        if ($latMissing || ($addressChanged && !$latLngProvided)) {
            GeocodeListingJob::dispatchSync($listing->id, $addressChanged);
            return;
        }

        if ($latLngProvided && !$listing->geocoded_at) {
            $listing->forceFill(['geocoded_at' => now()])->saveQuietly();
        }
    }
}
