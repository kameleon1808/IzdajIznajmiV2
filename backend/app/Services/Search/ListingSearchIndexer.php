<?php

namespace App\Services\Search;

use App\Models\Listing;
use App\Services\ListingStatusService;

class ListingSearchIndexer
{
    public function __construct(private readonly SearchDriver $driver) {}

    public function indexListingById(int $listingId): void
    {
        $listing = Listing::query()
            ->with(['facilities', 'owner:id,landlord_verification_status,landlord_verified_at'])
            ->find($listingId);

        if (! $listing) {
            $this->driver->removeListing($listingId);

            return;
        }

        if ($listing->status !== ListingStatusService::STATUS_ACTIVE) {
            $this->driver->removeListing($listingId);

            return;
        }

        $this->driver->indexListing($listing);
    }

    public function removeListingById(int $listingId): void
    {
        $this->driver->removeListing($listingId);
    }
}
