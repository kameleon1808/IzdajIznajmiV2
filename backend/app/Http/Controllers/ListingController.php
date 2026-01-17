<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Listing::query()
            ->where('status', 'published')
            ->with([
                'images' => function ($q) {
                    $q->where('processing_status', 'done')->orderBy('sort_order');
                },
                'facilities',
            ]);

        if (($category = $request->string('category')->toString()) && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($request->filled('priceMin')) {
            $query->where('price_per_night', '>=', (int) $request->input('priceMin'));
        }
        if ($request->filled('priceMax')) {
            $query->where('price_per_night', '<=', (int) $request->input('priceMax'));
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

        if ($guests = $request->input('guests')) {
            $query->where('beds', '>=', (int) $guests);
        }

        if ($facilities = $request->input('facilities')) {
            $facilityIds = (array) $facilities;
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
        if ($listing->status !== 'published' && !($user && ($isAdmin || $user->id === $listing->owner_id))) {
            abort(404);
        }
        return response()->json(new ListingResource($listing));
    }
}
