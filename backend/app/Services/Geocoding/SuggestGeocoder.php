<?php

namespace App\Services\Geocoding;

interface SuggestGeocoder
{
    /**
     * @return array<int, array{label: string, lat: float, lng: float, type: string}>
     */
    public function suggest(string $query, int $limit = 5): array;
}
