<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Jobs\ProcessListingImage;
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

        $listings = Listing::with([
            'images' => function ($q) {
                $q->orderBy('sort_order');
            },
            'facilities',
        ])
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
                'status' => 'draft',
            ]);

            $keepImages = [];
            $startOrder = 0;
            $coverIndex = $data['coverIndex'] ?? null;
            $uploaded = $this->storeUploadedImages($request->file('images', []), $listing, $coverIndex, $startOrder);
            $this->syncImages($listing, $keepImages, $uploaded);
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

            $existing = $listing->images()->get();
            $keepInput = collect($data['keepImages'] ?? [])->map(function ($item) {
                return [
                    'url' => $item['url'],
                    'sort_order' => $item['sortOrder'] ?? 0,
                    'is_cover' => (bool) ($item['isCover'] ?? false),
                ];
            });

            if ($keepInput->isEmpty()) {
                $keepInput = $existing->map(fn ($img) => [
                    'url' => $img->url,
                    'sort_order' => $img->sort_order,
                    'is_cover' => (bool) $img->is_cover,
                ]);
            }

            $removedUrls = collect($data['removeImageUrls'] ?? []);
            $keepInput = $keepInput->reject(fn ($item) => $removedUrls->contains($item['url']))->values();

            $newUploads = $this->storeUploadedImages($request->file('images', []), $listing, null, $keepInput->count());

            $this->deleteImages($listing, $removedUrls->all());
            $this->syncImages($listing, $keepInput->toArray(), $newUploads);

            if (array_key_exists('facilities', $data)) {
                $this->syncFacilities($listing, $data['facilities'] ?? []);
            }
        });

        $listing->refresh()->load(['images', 'facilities']);

        return response()->json(new ListingResource($listing));
    }

    public function publish(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        if (!in_array($listing->status, ['draft', 'published'])) {
            return response()->json(['message' => 'Cannot publish from current status'], 422);
        }
        $listing->update([
            'status' => 'published',
            'published_at' => now(),
            'archived_at' => null,
        ]);
        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    public function unpublish(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        if ($listing->status !== 'published') {
            return response()->json(['message' => 'Only published listings can be unpublished'], 422);
        }
        $listing->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    public function archive(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        if ($listing->status === 'archived') {
            return response()->json(new ListingResource($listing));
        }
        $listing->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    public function restore(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        if ($listing->status !== 'archived') {
            return response()->json(['message' => 'Only archived listings can be restored'], 422);
        }
        $listing->update([
            'status' => 'draft',
            'archived_at' => null,
        ]);
        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    private function ensureOwnerOrAdmin($user, Listing $listing): void
    {
        abort_unless($user && ($user->role === 'admin' || $user->id === $listing->owner_id), 403, 'Forbidden');
    }

    private function syncImages(Listing $listing, array $keepImages, array $newUploads): void
    {
        $existing = $listing->images()->get();
        $keepUrls = collect($keepImages)->pluck('url');
        $newUrls = collect($newUploads)->pluck('url');

        // delete removed (exclude freshly uploaded)
        $existing->filter(fn ($img) => !$keepUrls->contains($img->url) && !$newUrls->contains($img->url))->each->delete();

        // update kept
        foreach ($keepImages as $img) {
            $record = $existing->firstWhere('url', $img['url']);
            if ($record) {
                $record->update([
                    'sort_order' => $img['sort_order'] ?? 0,
                    'is_cover' => $img['is_cover'] ?? false,
                ]);
            }
        }

        // ensure new uploads are sorted
        foreach ($newUploads as $upload) {
            $upload->update([
                'sort_order' => $upload->sort_order,
                'is_cover' => $upload->is_cover,
            ]);
        }

        $imagesAll = $listing->images()->orderBy('sort_order')->get();
        if ($imagesAll->isNotEmpty()) {
            $cover = $imagesAll->firstWhere('is_cover', true) ?? $imagesAll->first();
            $listing->update(['cover_image' => $cover?->url]);
        }
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
        $listing->images()->whereIn('url', $urls)->delete();
    }

    private function storeUploadedImages(array $files, Listing $listing, ?int $coverIndex = null, int $startOrder = 0): array
    {
        $uploads = [];
        $disk = Storage::disk('public');
        $directory = "listings/{$listing->id}/original";
        foreach ($files as $index => $file) {
            $uuid = Str::uuid()->toString();
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $path = "{$directory}/{$uuid}.{$ext}";
            $disk->putFileAs($directory, $file, "{$uuid}.{$ext}");
            $isCover = $coverIndex !== null ? $coverIndex === $index : false;
            $image = ListingImage::create([
                'listing_id' => $listing->id,
                'url' => $disk->url($path),
                'sort_order' => $startOrder + $index,
                'is_cover' => $isCover,
                'processing_status' => 'pending',
            ]);
            ProcessListingImage::dispatch($image->id, $path);
            $uploads[] = $image;
        }

        return $uploads;
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
