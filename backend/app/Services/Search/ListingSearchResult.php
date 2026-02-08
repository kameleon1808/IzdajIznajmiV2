<?php

namespace App\Services\Search;

class ListingSearchResult
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $meta
     * @param  array<string, array<int, array{value: string, count: int}>>  $facets
     */
    public function __construct(
        public array $items,
        public array $meta,
        public array $facets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => $this->meta,
            'facets' => $this->facets,
        ];
    }
}
