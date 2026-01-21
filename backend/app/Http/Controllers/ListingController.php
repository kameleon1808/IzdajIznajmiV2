<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingSearchService;
use App\Services\ListingStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(private readonly ListingSearchService $searchService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('perPage', 10);
        $perPage = min(max($perPage, 1), 50);

        $filters = [
            'status' => $request->input('status'),
            'category' => $request->input('category'),
            'priceMin' => $request->input('priceMin'),
            'priceMax' => $request->input('priceMax'),
            'rooms' => $request->input('rooms'),
            'areaMin' => $request->input('areaMin'),
            'areaMax' => $request->input('areaMax'),
            'instantBook' => $request->boolean('instantBook'),
            'rating' => $request->input('rating'),
            'location' => $request->string('location')->toString(),
            'city' => $request->string('city')->toString(),
            'guests' => $request->input('guests'),
            'amenities' => $request->input('amenities'),
            'facilities' => $request->input('facilities'),
            'centerLat' => $request->input('centerLat'),
            'centerLng' => $request->input('centerLng'),
            'radiusKm' => $request->input('radiusKm'),
        ];

        $listings = $this->searchService->search($filters, $perPage);

        return ListingResource::collection($listings)->response();
    }

    public function show(Listing $listing): JsonResponse
    {
        $listing->load([
            'images' => fn ($q) => $q->where('processing_status', 'done')->orderBy('sort_order'),
            'facilities',
            'owner:id,full_name,name',
        ]);
        $user = request()->user();
        $isAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin');
        if ($listing->status !== ListingStatusService::STATUS_ACTIVE && !($user && ($isAdmin || $user->id === $listing->owner_id))) {
            abort(404);
        }
        return response()->json(new ListingResource($listing));
    }
}
