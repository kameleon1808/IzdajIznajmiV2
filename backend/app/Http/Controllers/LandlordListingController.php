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
use Intervention\Image\ImageManager;

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
            ]);

            $uploaded = $this->storeUploadedImages($request->file('images', []), $listing->id, $data['coverIndex'] ?? null);
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

            $existing = $listing->images()->get(['id', 'url', 'sort_order', 'is_cover']);
            $keep = collect($data['keepImages'] ?? [])->map(function ($item) {
                return [
                    'url' => $item['url'],
                    'sort_order' => $item['sortOrder'] ?? 0,
                    'is_cover' => (bool) ($item['isCover'] ?? false),
                ];
            });

            if ($keep->isEmpty()) {
                $keep = $existing->map(fn ($img) => [
                    'url' => $img->url,
                    'sort_order' => $img->sort_order,
                    'is_cover' => (bool) $img->is_cover,
                ]);
            }

            $removedUrls = collect($data['removeImageUrls'] ?? []);
            $keep = $keep->reject(fn ($item) => $removedUrls->contains($item['url']))->values();

            $newUploads = $this->storeUploadedImages($request->file('images', []), $listing->id, null, $keep->count());
            $final = $keep->toArray();
            $final = array_merge($final, $newUploads);

            $this->deleteImages($listing, $removedUrls->all());
            $this->syncImages($listing, $final);

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
        $coverSet = false;
        foreach ($images as $index => $image) {
            $url = is_array($image) ? ($image['url'] ?? '') : $image;
            $sortOrder = is_array($image) ? ($image['sort_order'] ?? $index) : $index;
            $isCover = is_array($image) ? ($image['is_cover'] ?? false) : ($index === 0);
            if (!$coverSet && ($isCover || ($index === 0 && !$this->hasCover($images)))) {
                $isCover = true;
                $coverSet = true;
            } elseif ($coverSet && $isCover) {
                $isCover = false;
            }
            ListingImage::create([
                'listing_id' => $listing->id,
                'url' => $url,
                'sort_order' => $sortOrder,
                'is_cover' => $isCover,
            ]);
        }

        if (!empty($images)) {
            $cover = collect($images)->first(fn ($img) => is_array($img) ? ($img['is_cover'] ?? false) : false);
            $coverUrl = $cover
                ? (is_array($cover) ? $cover['url'] : $cover)
                : (is_array($images[0]) ? ($images[0]['url'] ?? null) : $images[0]);
            $listing->update(['cover_image' => $coverUrl]);
        }
    }

    private function hasCover(array $images): bool
    {
        return collect($images)->contains(function ($img) {
            return is_array($img) ? ($img['is_cover'] ?? false) : false;
        });
    }

    private function storeUploadedImages(array $files, int $listingId, ?int $coverIndex = null, int $startOrder = 0): array
    {
        $urls = [];
        foreach ($files as $index => $file) {
            $order = $startOrder + $index;
            $converted = $this->processAndStoreImage($file, $listingId);
            $urls[] = [
                'url' => $converted,
                'sort_order' => $order,
                'is_cover' => $coverIndex !== null ? $coverIndex === $index : false,
            ];
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

    private function processAndStoreImage($file, int $listingId): string
    {
        $optimize = filter_var(env('IMAGE_OPTIMIZE', true), FILTER_VALIDATE_BOOL);
        $maxWidth = (int) env('IMAGE_MAX_WIDTH', 1600);
        $quality = (int) env('IMAGE_WEBP_QUALITY', 80);

        $name = Str::uuid()->toString();
        $disk = Storage::disk('public');
        $directory = "listings/{$listingId}";

        if ($optimize) {
            try {
                $manager = new ImageManager(['driver' => 'gd']);
                $image = $manager->read($file->getPathname());
                if ($maxWidth > 0 && $image->width() > $maxWidth) {
                    $image = $image->scale($maxWidth, null);
                }
                $encoded = $image->toWebp($quality);
                $path = "{$directory}/{$name}.webp";
                $disk->put($path, (string) $encoded);
                return $disk->url($path);
            } catch (\Throwable $e) {
                // fallback to original
            }
        }

        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $path = "{$directory}/{$name}.{$ext}";
        $disk->putFileAs($directory, $file, "{$name}.{$ext}");
        return $disk->url($path);
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
