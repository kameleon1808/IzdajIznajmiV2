<?php

return [
    // Maximum allowed radius for geo queries (km)
    'max_radius_km' => (float) env('SEARCH_MAX_RADIUS_KM', 50.0),

    // Maximum number of map pins returned when mapMode=true
    'max_map_results' => (int) env('SEARCH_MAX_MAP_RESULTS', 300),
];
