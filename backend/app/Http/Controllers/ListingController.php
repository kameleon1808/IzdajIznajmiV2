<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Listing::query()
            ->with([
                'images' => function ($q) {
                    $q->where('processing_status', 'done')->orderBy('sort_order');
                },
                'facilities',
            ]);

        $statuses = array_filter((array) $request->input('status'));
        $allowedStatuses = app(ListingStatusService::class)->allowedStatuses();
        if (!empty($statuses) && !in_array('all', $statuses, true)) {
            $query->whereIn('status', array_intersect($statuses, $allowedStatuses));
        } else {
            $query->where('status', ListingStatusService::STATUS_ACTIVE);
        }

        if (($category = $request->string('category')->toString()) && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($request->filled('priceMin')) {
            $query->where('price_per_night', '>=', (int) $request->input('priceMin'));
        }
        if ($request->filled('priceMax')) {
            $query->where('price_per_night', '<=', (int) $request->input('priceMax'));
        }

        if ($request->filled('rooms')) {
            $query->where('rooms', '>=', (int) $request->input('rooms'));
        }

        if ($request->filled('areaMin')) {
            $query->where('area', '>=', (int) $request->input('areaMin'));
        }
        if ($request->filled('areaMax')) {
            $query->where('area', '<=', (int) $request->input('areaMax'));
        }

        if ($request->boolean('instantBook')) {
            $query->where('instant_book', true);
        }

        if ($rating = $request->input('rating')) {
            $query->where('rating', '>=', (float) $rating);
        }

        if ($location = $request->string('location')->toString()) {
            $query->where(function ($builder) use ($location) {
                $builder->where('city', 'like', "%{$location}%")
                    ->orWhere('country', 'like', "%{$location}%");
            });
        }

        if ($city = $request->string('city')->toString()) {
            $tokens = collect(preg_split('/,/', $city))
                ->filter()
                ->map(fn ($token) => trim($token))
                ->values();

            $query->where(function ($builder) use ($city, $tokens) {
                $builder->where('city', 'like', "%{$city}%")
                    ->orWhere('country', 'like', "%{$city}%");

                $tokens->each(function ($token) use ($builder) {
                    $builder->orWhere('city', 'like', "%{$token}%")
                        ->orWhere('country', 'like', "%{$token}%");
                });
            });
        }

        if ($guests = $request->input('guests')) {
            $query->where('beds', '>=', (int) $guests);
        }

        $amenities = $request->input('amenities');
        $facilities = $request->input('facilities');
        $amenitiesToApply = $amenities ?? $facilities;
        if ($amenitiesToApply) {
            $facilityIds = (array) $amenitiesToApply;
            // ANY match for facilities for now; ALL can be swapped later.
            $query->whereHas('facilities', function ($builder) use ($facilityIds) {
                $builder->whereIn('name', $facilityIds);
            });
        }

        $perPage = (int) $request->input('perPage', 10);
        $perPage = min(max($perPage, 1), 50);
        $listings = $query->orderByDesc('created_at')->paginate($perPage);

        return ListingResource::collection($listings)->response();
    }

    public function show(Listing $listing): JsonResponse
    {
        $listing->load(['images' => fn ($q) => $q->where('processing_status', 'done')->orderBy('sort_order'), 'facilities']);
        $user = request()->user();
        $isAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin');
        if ($listing->status !== ListingStatusService::STATUS_ACTIVE && !($user && ($isAdmin || $user->id === $listing->owner_id))) {
            abort(404);
        }
        return response()->json(new ListingResource($listing));
    }
}
