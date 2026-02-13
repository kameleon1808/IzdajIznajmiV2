<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingEventService;
use App\Services\ListingSearchService;
use App\Services\ListingStatusService;
use App\Services\SavedSearchNormalizer;
use App\Services\SearchFilterSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(private readonly ListingSearchService $searchService) {}

    public function index(Request $request, SearchFilterSnapshotService $snapshots, SavedSearchNormalizer $normalizer): JsonResponse
    {
        $perPage = (int) $request->input('perPage', 10);
        $perPage = min(max($perPage, 1), 50);
        $mapMode = $request->boolean('mapMode', false);
        if ($mapMode) {
            $perPage = min($perPage, 300);
        }

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
            'location' => $request->string('location')->toString() ?: $request->string('q')->toString(),
            'city' => $request->string('city')->toString() ?: $request->string('q')->toString(),
            'guests' => $request->input('guests'),
            'amenities' => $request->input('amenities'),
            'facilities' => $request->input('facilities'),
            'centerLat' => $request->input('centerLat'),
            'centerLng' => $request->input('centerLng'),
            'radiusKm' => $request->input('radiusKm'),
            'mapMode' => $mapMode,
        ];

        if ($mapMode && (! is_numeric($filters['centerLat']) || ! is_numeric($filters['centerLng']))) {
            return response()->json(['message' => 'Map view requires centerLat and centerLng'], 422);
        }

        if ($request->boolean('recordSearch', false)) {
            $user = $request->user();
            if ($user) {
                $normalized = $normalizer->normalize($filters);
                $snapshots->record($user, $normalized);
            }
        }

        $listings = $this->searchService->search($filters, $perPage);

        if ($mapMode) {
            $items = $listings->getCollection()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'lat' => $item->lat,
                    'lng' => $item->lng,
                    'pricePerNight' => $item->price_per_night,
                    'coverImage' => $item->cover_image,
                    'city' => $item->city,
                    'distanceKm' => $item->distance_km !== null ? round((float) $item->distance_km, 2) : null,
                ];
            });

            return response()->json([
                'data' => $items,
                'meta' => [
                    'total' => $listings->total(),
                    'count' => $listings->count(),
                ],
            ]);
        }

        return ListingResource::collection($listings)->response();
    }

    public function show(Listing $listing, ListingEventService $events): JsonResponse
    {
        $user = request()->user();
        $isAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin');
        $isOwner = $user && $user->id === $listing->owner_id;

        $listing->load([
            'images' => function ($q) use ($isAdmin, $isOwner) {
                if (! $isAdmin && ! $isOwner) {
                    $q->where('processing_status', 'done');
                }
                $q->orderBy('sort_order');
            },
            'facilities',
            'owner:id,full_name,name,verification_status,verified_at,is_suspicious,badge_override_json',
            'owner.landlordMetric:landlord_id,avg_rating_30d,all_time_avg_rating,ratings_count,median_response_time_minutes,completed_transactions_count,updated_at',
        ])->loadCount('listingRatings as listing_rating_count')
            ->loadAvg('listingRatings as listing_rating_avg', 'rating');
        if ($listing->status !== ListingStatusService::STATUS_ACTIVE && ! ($user && ($isAdmin || $user->id === $listing->owner_id))) {
            abort(404);
        }

        if ($user) {
            $events->recordView($user, $listing);
        }

        return response()->json(new ListingResource($listing));
    }
}
