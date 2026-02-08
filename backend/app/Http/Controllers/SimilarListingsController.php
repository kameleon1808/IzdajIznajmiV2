<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\SimilarListingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimilarListingsController extends Controller
{
    public function index(Request $request, Listing $listing, SimilarListingsService $service): JsonResponse
    {
        $limit = (int) $request->input('limit', 8);
        $limit = min(max($limit, 1), 20);

        $items = $service->similarTo($listing, $limit);

        return response()->json([
            'data' => ListingResource::collection($items)->resolve(),
        ]);
    }
}
