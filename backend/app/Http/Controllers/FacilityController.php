<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Support\ListingAmenityNormalizer;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
    public function index(): JsonResponse
    {
        $names = Facility::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => ListingAmenityNormalizer::canonicalize((string) $name) ?? (string) $name)
            ->unique(fn ($name) => ListingAmenityNormalizer::normalizeToken((string) $name))
            ->values()
            ->all();

        return response()->json([
            'data' => collect($names)->map(fn ($name) => ['name' => $name])->values(),
        ]);
    }
}
