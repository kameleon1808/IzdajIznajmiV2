<?php

namespace App\Services\Search;

use App\Models\Listing;

interface SearchDriver
{
    public function searchListings(array $filters, int $page, int $perPage): ListingSearchResult;

    /**
     * @return array<int, array{label: string, type: string, value: string}>
     */
    public function suggest(string $query, int $limit): array;

    public function indexListing(Listing $listing): void;

    public function removeListing(int $listingId): void;

    public function configureIndex(): void;

    public function resetIndex(): void;
}
