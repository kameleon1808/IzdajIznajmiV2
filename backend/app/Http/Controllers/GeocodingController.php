<?php

namespace App\Http\Controllers;

use App\Services\Geocoding\Geocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodingController extends Controller
{
    public function lookup(Request $request, Geocoder $geocoder): JsonResponse
    {
        $query = trim((string) ($request->input('q') ?? $request->input('address') ?? ''));
        if ($query === '') {
            return response()->json(['message' => 'Query is required'], 422);
        }

        $result = $geocoder->geocode($query);
        if (!$result) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        return response()->json([
            'lat' => $result['lat'],
            'lng' => $result['lng'],
        ]);
    }
}
