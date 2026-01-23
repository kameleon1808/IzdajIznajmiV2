<?php

namespace App\Services\Geocoding;

class FakeSuggestGeocoder implements SuggestGeocoder
{
    /** @var array<int, array{label: string, lat: float, lng: float, type: string}> */
    private array $places;

    public function __construct()
    {
        // Deterministic, hand-picked Balkan cities + a couple streets for realism
        $this->places = [
            ['label' => 'Belgrade, Serbia', 'lat' => 44.8125, 'lng' => 20.4612, 'type' => 'city'],
            ['label' => 'Novi Sad, Serbia', 'lat' => 45.2671, 'lng' => 19.8335, 'type' => 'city'],
            ['label' => 'Niš, Serbia', 'lat' => 43.3209, 'lng' => 21.8958, 'type' => 'city'],
            ['label' => 'Kragujevac, Serbia', 'lat' => 44.0128, 'lng' => 20.9114, 'type' => 'city'],
            ['label' => 'Subotica, Serbia', 'lat' => 46.1000, 'lng' => 19.6667, 'type' => 'city'],
            ['label' => 'Zagreb, Croatia', 'lat' => 45.8150, 'lng' => 15.9819, 'type' => 'city'],
            ['label' => 'Split, Croatia', 'lat' => 43.5081, 'lng' => 16.4402, 'type' => 'city'],
            ['label' => 'Sarajevo, Bosnia and Herzegovina', 'lat' => 43.8563, 'lng' => 18.4131, 'type' => 'city'],
            ['label' => 'Podgorica, Montenegro', 'lat' => 42.4304, 'lng' => 19.2594, 'type' => 'city'],
            ['label' => 'Skopje, North Macedonia', 'lat' => 41.9973, 'lng' => 21.4280, 'type' => 'city'],
            ['label' => 'Knez Mihailova, Belgrade', 'lat' => 44.8170, 'lng' => 20.4589, 'type' => 'address'],
            ['label' => 'Terazije, Belgrade', 'lat' => 44.8129, 'lng' => 20.4635, 'type' => 'address'],
            ['label' => 'Zeleni venac, Belgrade', 'lat' => 44.8120, 'lng' => 20.4565, 'type' => 'address'],
        ];
    }

    public function suggest(string $query, int $limit = 5): array
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $matches = array_values(array_filter($this->places, function ($place) use ($normalized) {
            return str_contains(mb_strtolower($place['label']), $normalized);
        }));

        return array_slice($matches, 0, max(1, $limit));
    }
}
