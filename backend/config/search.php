<?php

return [
    // Search driver: sql|meili
    'driver' => env('SEARCH_DRIVER', 'sql'),

    // Maximum allowed radius for geo queries (km)
    'max_radius_km' => (float) env('SEARCH_MAX_RADIUS_KM', 50.0),

    // Maximum number of map pins returned when mapMode=true
    'max_map_results' => (int) env('SEARCH_MAX_MAP_RESULTS', 300),

    // Max per-page for search endpoints
    'max_per_page' => (int) env('SEARCH_MAX_PER_PAGE', 50),

    'meili' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index' => env('MEILISEARCH_INDEX', 'listings'),
    ],
];
