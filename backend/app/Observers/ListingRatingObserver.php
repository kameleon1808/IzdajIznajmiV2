<?php

namespace App\Observers;

use App\Jobs\IndexListingJob;
use App\Models\Listing;
use App\Models\ListingRating;

class ListingRatingObserver
{
    public function created(ListingRating $listingRating): void
    {
        $this->syncListingRating($listingRating->listing_id);
    }

    public function updated(ListingRating $listingRating): void
    {
        $this->syncListingRating($listingRating->listing_id);
    }

    public function deleted(ListingRating $listingRating): void
    {
        $this->syncListingRating($listingRating->listing_id);
    }

    private function syncListingRating(int $listingId): void
    {
        $avg = ListingRating::where('listing_id', $listingId)->avg('rating') ?? 0.0;
        $count = ListingRating::where('listing_id', $listingId)->count();

        Listing::where('id', $listingId)->update([
            'rating' => round((float) $avg, 1),
            'reviews_count' => $count,
        ]);

        IndexListingJob::dispatch($listingId);
    }
}
