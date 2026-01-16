<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Facility;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LandlordListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless(in_array($user->role, ['landlord', 'admin']), 403, 'Forbidden');

        $ownerId = $user->role === 'admin' && $request->filled('ownerId')
            ? (int) $request->input('ownerId')
            : $user->id;

        $listings = Listing::with(['images', 'facilities'])
            ->where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(ListingResource::collection($listings));
    }

    public function store(StoreListingRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['landlord', 'admin']), 403, 'Forbidden');

        $data = $request->validated();
        $listing = DB::transaction(function () use ($data, $user, $request) {
            $listing = Listing::create([
                'owner_id' => $user->id,
                'title' => $data['title'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'price_per_night' => $data['pricePerNight'],
                'rating' => $data['rating'] ?? 4.7,
                'reviews_count' => $data['reviews_count'] ?? 0,
                'description' => $data['description'] ?? null,
                'beds' => $data['beds'],
                'baths' => $data['baths'],
                'category' => $data['category'],
                'instant_book' => $data['instantBook'] ?? false,
            ]);

            $uploaded = $this->storeUploadedImages($request->file('images', []), $listing->id);
            $this->syncImages($listing, $uploaded);
            $this->syncFacilities($listing, $data['facilities'] ?? []);

            return $listing->load(['images', 'facilities']);
        });

        return response()->json(new ListingResource($listing), 201);
    }

    public function update(UpdateListingRequest $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $data = $request->validated();

        $payload = [];
        $map = [
            'title' => 'title',
            'pricePerNight' => 'price_per_night',
            'category' => 'category',
            'city' => 'city',
            'country' => 'country',
            'address' => 'address',
            'description' => 'description',
            'beds' => 'beds',
            'baths' => 'baths',
            'lat' => 'lat',
            'lng' => 'lng',
            'instantBook' => 'instant_book',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $payload[$column] = $data[$input];
            }
        }

        DB::transaction(function () use ($listing, $data, $payload, $request) {
            if (!empty($payload)) {
                $listing->update($payload);
            }

            $existingUrls = $listing->images()->pluck('url')->all();
            $keepUrls = collect($data['keepImageUrls'] ?? [])
                ->filter()
                ->values();

            if ($keepUrls->isEmpty()) {
                $keepUrls = collect($existingUrls)->diff($data['removeImageUrls'] ?? [])->values();
            }

            $removed = collect($existingUrls)
                ->diff($keepUrls)
                ->merge($data['removeImageUrls'] ?? [])
                ->unique()
                ->values();

            $newUploads = $this->storeUploadedImages($request->file('images', []), $listing->id);
            $finalUrls = array_values(array_filter(array_merge($keepUrls->all(), $newUploads)));

            $this->deleteImages($listing, $removed->all());
            $this->syncImages($listing, $finalUrls);

            if (array_key_exists('facilities', $data)) {
                $this->syncFacilities($listing, $data['facilities'] ?? []);
            }
        });

        $listing->refresh()->load(['images', 'facilities']);

        return response()->json(new ListingResource($listing));
    }

    private function syncImages(Listing $listing, array $images): void
    {
        $listing->images()->delete();
        foreach ($images as $index => $url) {
            ListingImage::create([
                'listing_id' => $listing->id,
                'url' => $url,
                'sort_order' => $index,
            ]);
        }

        if (!empty($images)) {
            $listing->update(['cover_image' => $images[0]]);
        }
    }

    private function storeUploadedImages(array $files, int $listingId): array
    {
        $urls = [];
        foreach ($files as $file) {
            $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $path = "listings/{$listingId}/{$name}";
            Storage::disk('public')->putFileAs("listings/{$listingId}", $file, $name);
            $urls[] = Storage::url($path);
        }
        return $urls;
    }

    private function deleteImages(Listing $listing, array $urls): void
    {
        foreach ($urls as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            if (!$path) {
                continue;
            }
            $relative = ltrim(str_replace('/storage/', '', $path), '/');
            if (!str_starts_with($relative, "listings/{$listing->id}/")) {
                continue;
            }
            Storage::disk('public')->delete($relative);
        }
    }

    private function syncFacilities(Listing $listing, array $facilities): void
    {
        $facilityIds = collect($facilities)
            ->filter()
            ->map(fn ($name) => Facility::firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();

        $listing->facilities()->sync($facilityIds);
    }
}
