<?php

namespace App\Services\Geocoding;

interface Geocoder
{
    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array;
}
