<?php

namespace App\Http\Controllers;

use App\Services\SavedSearchNormalizer;
use App\Services\Search\SearchDriver;
use App\Services\SearchFilterSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly SearchDriver $driver) {}

    public function listings(Request $request, SearchFilterSnapshotService $snapshots, SavedSearchNormalizer $normalizer): JsonResponse
    {
        $perPage = (int) $request->input('perPage', 10);
        $perPage = min(max($perPage, 1), (int) config('search.max_per_page', 50));
        $page = (int) $request->input('page', 1);
        $page = max($page, 1);

        $filters = [
            'q' => $request->string('q')->toString() ?: $request->string('location')->toString(),
            'category' => $request->input('category'),
            'guests' => $request->input('guests'),
            'city' => $request->input('city'),
            'status' => $request->input('status'),
            'rooms' => $request->input('rooms'),
            'amenities' => $request->input('amenities'),
            'priceMin' => $request->input('priceMin'),
            'priceMax' => $request->input('priceMax'),
            'priceBucket' => $request->input('priceBucket') ?? $request->input('price_bucket'),
            'areaMin' => $request->input('areaMin'),
            'areaMax' => $request->input('areaMax'),
            'areaBucket' => $request->input('areaBucket') ?? $request->input('area_bucket'),
            'sort' => $request->input('sort'),
            'instantBook' => $request->boolean('instantBook'),
            'rating' => $request->input('rating'),
        ];

        if ($request->boolean('recordSearch', false)) {
            $user = $request->user();
            if ($user) {
                $normalized = $normalizer->normalize($filters);
                $snapshots->record($user, $normalized);
            }
        }

        $result = $this->driver->searchListings($filters, $page, $perPage);

        return response()->json($result->toArray());
    }

    public function suggest(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();
        $limit = (int) $request->input('limit', 8);
        $limit = min(max($limit, 1), 20);

        if ($query === '') {
            return response()->json([]);
        }

        return response()->json($this->driver->suggest($query, $limit));
    }
}
