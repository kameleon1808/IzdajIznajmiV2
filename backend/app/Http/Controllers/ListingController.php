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
        $query = Listing::query()->with(['images', 'facilities']);

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

        $listings = $query->orderByDesc('created_at')->get();

        return response()->json(ListingResource::collection($listings));
    }

    public function show(Listing $listing): JsonResponse
    {
        $listing->load(['images', 'facilities']);
        return response()->json(new ListingResource($listing));
    }
}
