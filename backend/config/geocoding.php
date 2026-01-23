<?php

return [
    'driver' => env('GEOCODER_DRIVER', 'fake'),
    'cache_ttl_minutes' => (int) env('GEOCODER_CACHE_TTL', 1440),

    'suggest_driver' => env('GEOCODER_SUGGEST_DRIVER', env('GEOCODER_DRIVER', 'fake')),
    'suggest_cache_ttl_minutes' => (int) env('GEOCODER_SUGGEST_CACHE_TTL', 15),

    'fake' => [
        'base_lat' => (float) env('FAKE_GEOCODER_BASE_LAT', 45.0),
        'base_lng' => (float) env('FAKE_GEOCODER_BASE_LNG', 15.0),
        'spread_km' => (float) env('FAKE_GEOCODER_SPREAD_KM', 120.0),
    ],

    'nominatim' => [
        'endpoint' => env('GEOCODER_NOMINATIM_URL', 'https://nominatim.openstreetmap.org/search'),
        'email' => env('GEOCODER_NOMINATIM_EMAIL'),
        'countrycodes' => env('GEOCODER_NOMINATIM_COUNTRIES'),
        'rate_limit_ms' => (int) env('GEOCODER_NOMINATIM_RATE_LIMIT_MS', 1200),
        'timeout' => (int) env('GEOCODER_NOMINATIM_TIMEOUT', 8),
    ],
];
