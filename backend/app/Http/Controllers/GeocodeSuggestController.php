<?php

namespace App\Http\Controllers;

use App\Services\Geocoding\SuggestGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeSuggestController extends Controller
{
    public function suggest(Request $request, SuggestGeocoder $geocoder): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 5);
        $limit = max(1, min($limit, 10));

        if ($query === '') {
            return response()->json([], 200);
        }

        $results = $geocoder->suggest($query, $limit);

        return response()->json($results);
    }
}
