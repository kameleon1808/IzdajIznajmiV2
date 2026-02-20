<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Jobs\IndexListingJob;
use App\Jobs\ProcessListingImage;
use App\Models\Facility;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ListingAmenityNormalizer;
use App\Support\MediaUrl;
use App\Services\FraudSignalService;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use App\Services\StructuredLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LandlordListingController extends Controller
{
    public function __construct(
        private readonly ListingStatusService $statusService,
        private readonly ListingAddressGuardService $addressGuard,
        private readonly StructuredLogger $log,
        private FraudSignalService $fraudSignals
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Forbidden');

        $ownerId = $this->isAdmin($user) && $request->filled('ownerId')
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
        abort_unless($user && $this->userHasRole($user, ['landlord', 'admin']), 403, 'Forbidden');

        $data = $request->validated();
        $addressKey = $this->addressGuard->normalizeAddressKey($data['address'], $data['city'], $data['country']);
        $rooms = $data['rooms'] ?? $data['beds'];

        $this->recordDuplicateAddressAttempt($user, $addressKey);

        $listing = DB::transaction(function () use ($data, $user, $request, $addressKey, $rooms) {
            $listing = Listing::create([
                'owner_id' => $user->id,
                'title' => $data['title'],
                'address' => $data['address'],
                'address_key' => $addressKey,
                'city' => $data['city'],
                'country' => $data['country'],
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'geocoded_at' => (isset($data['lat'], $data['lng']) && $data['lat'] !== null && $data['lng'] !== null) ? now() : null,
                'price_per_night' => $data['pricePerNight'],
                'rating' => $data['rating'] ?? 4.7,
                'reviews_count' => $data['reviews_count'] ?? 0,
                'description' => $data['description'] ?? null,
                'beds' => $data['beds'],
                'baths' => $data['baths'],
                'rooms' => $rooms,
                'area' => $data['area'] ?? null,
                'floor' => $data['floor'] ?? null,
                'not_last_floor' => $data['notLastFloor'] ?? false,
                'not_ground_floor' => $data['notGroundFloor'] ?? false,
                'heating' => $data['heating'] ?? null,
                'condition' => $data['condition'] ?? null,
                'furnishing' => $data['furnishing'] ?? null,
                'category' => $data['category'],
                'instant_book' => $data['instantBook'] ?? false,
                'status' => ListingStatusService::STATUS_DRAFT,
                'location_source' => 'geocoded',
                'location_accuracy_m' => null,
                'location_overridden_at' => null,
            ]);

            $keepImages = [];
            $startOrder = 0;
            $coverIndex = $data['coverIndex'] ?? null;
            $uploaded = $this->storeUploadedImages($request->file('images', []), $listing, $coverIndex, $startOrder);
            $this->syncImages($listing, $keepImages, $uploaded);
            $this->syncFacilities($listing, $data['facilities'] ?? []);

            return $listing->load(['images', 'facilities']);
        });

        $listing->setAttribute('warnings', []);

        $this->log->info('listing_created', [
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'status' => $listing->status,
        ]);

        return response()->json(new ListingResource($listing), 201);
    }

    public function update(UpdateListingRequest $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $data = $request->validated();

        $addressChanged = array_key_exists('address', $data) || array_key_exists('city', $data) || array_key_exists('country', $data);
        $newAddress = $data['address'] ?? $listing->address;
        $newCity = $data['city'] ?? $listing->city;
        $newCountry = $data['country'] ?? $listing->country;
        $addressKey = $this->addressGuard->normalizeAddressKey($newAddress, $newCity, $newCountry);
        $warnings = [];
        if ($addressChanged && $listing->status === ListingStatusService::STATUS_ACTIVE) {
            $warnings = $this->addressGuard->guardActiveAddress($listing, $addressKey);
        }

        if ($addressChanged) {
            $this->recordDuplicateAddressAttempt($request->user(), $addressKey);
        }

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
            'rooms' => 'rooms',
            'area' => 'area',
            'floor' => 'floor',
            'notLastFloor' => 'not_last_floor',
            'notGroundFloor' => 'not_ground_floor',
            'heating' => 'heating',
            'condition' => 'condition',
            'furnishing' => 'furnishing',
            'lat' => 'lat',
            'lng' => 'lng',
            'instantBook' => 'instant_book',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $payload[$column] = $data[$input];
            }
        }

        $addressKeyChanged = $addressChanged && $listing->address_key !== $addressKey;
        $latProvided = array_key_exists('lat', $payload) || array_key_exists('lng', $payload);
        $resetManualLocation = $addressKeyChanged && $listing->location_source === 'manual';
        if ($latProvided) {
            $payload['geocoded_at'] = (isset($payload['lat'], $payload['lng']) && $payload['lat'] !== null && $payload['lng'] !== null) ? now() : null;
            $payload['location_source'] = 'geocoded';
            $payload['location_overridden_at'] = null;
        } elseif ($addressKeyChanged) {
            $payload['geocoded_at'] = null;
        }

        if ($resetManualLocation) {
            $payload['location_source'] = 'geocoded';
            $payload['location_overridden_at'] = null;
            if (! $latProvided) {
                $payload['lat'] = null;
                $payload['lng'] = null;
            }
        }

        if ($addressChanged) {
            $payload['address_key'] = $addressKey;
        }

        DB::transaction(function () use ($listing, $data, $payload, $request) {
            if (! empty($payload)) {
                $listing->update($payload);
            }

            $existing = $listing->images()->get();
            $keepInput = collect($data['keepImages'] ?? [])->map(function ($item) {
                $normalizedUrl = MediaUrl::normalize($item['url'] ?? null);

                return [
                    'url' => $normalizedUrl,
                    'sort_order' => $item['sortOrder'] ?? 0,
                    'is_cover' => (bool) ($item['isCover'] ?? false),
                ];
            })->filter(fn ($item) => ! empty($item['url']))->values();

            if ($keepInput->isEmpty()) {
                $keepInput = $existing->map(fn ($img) => [
                    'url' => MediaUrl::normalize($img->url),
                    'sort_order' => $img->sort_order,
                    'is_cover' => (bool) $img->is_cover,
                ]);
            }

            $removedUrls = collect($data['removeImageUrls'] ?? [])
                ->map(fn ($url) => MediaUrl::normalize(is_string($url) ? $url : null))
                ->filter()
                ->values();
            $keepInput = $keepInput->reject(fn ($item) => $removedUrls->contains($item['url']))->values();

            $newUploads = $this->storeUploadedImages($request->file('images', []), $listing, null, $keepInput->count());

            $this->deleteImages($listing, $removedUrls->all());
            $this->syncImages($listing, $keepInput->toArray(), $newUploads);

            if (array_key_exists('facilities', $data)) {
                $this->syncFacilities($listing, $data['facilities'] ?? []);
            }
        });

        $listing->refresh()->load(['images', 'facilities']);
        $listing->setAttribute('warnings', $warnings);

        $this->log->info('listing_updated', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
            'changed_fields' => array_keys($payload),
        ]);

        return response()->json(new ListingResource($listing));
    }

    public function publish(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        $addressKey = $this->addressGuard->normalizeAddressKey($listing->address, $listing->city, $listing->country);
        $warnings = $this->addressGuard->guardActiveAddress($listing, $addressKey);

        try {
            $this->statusService->markActive($listing);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Cannot publish from current status'], 422);
        }

        $listing->update(['address_key' => $addressKey]);
        $listing = $listing->fresh(['images', 'facilities']);
        $listing->setAttribute('warnings', $warnings);

        $this->log->info('listing_published', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
            'status' => $listing->status,
        ]);

        return response()->json(new ListingResource($listing));
    }

    public function unpublish(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        try {
            $this->statusService->markPaused($listing);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Only active listings can be paused'], 422);
        }

        $listing->refresh()->load(['images', 'facilities']);

        $this->log->info('listing_unpublished', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
            'status' => $listing->status,
        ]);

        return response()->json(new ListingResource($listing));
    }

    public function archive(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        if ($listing->status === ListingStatusService::STATUS_ARCHIVED) {
            return response()->json(new ListingResource($listing));
        }
        try {
            $this->statusService->markArchived($listing);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Cannot archive from current status'], 422);
        }

        $this->log->info('listing_archived', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    public function restore(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        try {
            $this->statusService->markDraft($listing);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Only archived or expired listings can be restored'], 422);
        }

        $this->log->info('listing_restored', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    public function markRented(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        try {
            $this->statusService->markRented($listing);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Cannot mark rented from current status'], 422);
        }

        $this->log->info('listing_marked_rented', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(new ListingResource($listing->fresh(['images', 'facilities'])));
    }

    public function markAvailable(Request $request, Listing $listing): JsonResponse
    {
        $this->authorize('update', $listing);
        $this->ensureOwnerOrAdmin($request->user(), $listing);
        $addressKey = $this->addressGuard->normalizeAddressKey($listing->address, $listing->city, $listing->country);
        $warnings = $this->addressGuard->guardActiveAddress($listing, $addressKey);

        try {
            $this->statusService->markActive($listing);
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Cannot activate from current status'], 422);
        }

        $listing->update(['address_key' => $addressKey]);
        $listing = $listing->fresh(['images', 'facilities']);
        $listing->setAttribute('warnings', $warnings);

        $this->log->info('listing_marked_available', [
            'listing_id' => $listing->id,
            'user_id' => $request->user()?->id,
            'warnings' => $warnings,
        ]);

        return response()->json(new ListingResource($listing));
    }

    private function ensureOwnerOrAdmin($user, Listing $listing): void
    {
        abort_unless($user && ($this->isAdmin($user) || $user->id === $listing->owner_id), 403, 'Forbidden');
    }

    private function syncImages(Listing $listing, array $keepImages, array $newUploads): void
    {
        $existing = $listing->images()->get();
        $existingByUrl = $existing->mapWithKeys(function ($img) {
            $normalized = MediaUrl::normalize($img->url);
            if ($normalized === null || $normalized === '') {
                return [];
            }

            return [$normalized => $img];
        });
        $keepUrls = collect($keepImages)
            ->map(fn ($img) => MediaUrl::normalize($img['url'] ?? null))
            ->filter()
            ->values();
        $newUrls = collect($newUploads)
            ->map(fn ($upload) => MediaUrl::normalize($upload->url))
            ->filter()
            ->values();

        // delete removed (exclude freshly uploaded)
        $existing
            ->filter(function ($img) use ($keepUrls, $newUrls) {
                $normalized = MediaUrl::normalize($img->url);

                return ! $keepUrls->contains($normalized) && ! $newUrls->contains($normalized);
            })
            ->each->delete();

        // update kept
        foreach ($keepImages as $img) {
            $normalizedUrl = MediaUrl::normalize($img['url'] ?? null);
            if (! $normalizedUrl) {
                continue;
            }

            $record = $existingByUrl->get($normalizedUrl);
            if ($record) {
                $record->update([
                    'url' => $normalizedUrl,
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
        $normalizedUrls = collect($urls)
            ->map(fn ($url) => MediaUrl::normalize(is_string($url) ? $url : null))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedUrls->isEmpty()) {
            return;
        }

        $existing = $listing->images()->get();
        $toDelete = $existing->filter(fn ($img) => $normalizedUrls->contains(MediaUrl::normalize($img->url)));

        foreach ($toDelete as $image) {
            $path = parse_url((string) MediaUrl::normalize($image->url), PHP_URL_PATH);
            if ($path) {
                $relative = ltrim(str_replace('/storage/', '', $path), '/');
                if (str_starts_with($relative, "listings/{$listing->id}/")) {
                    Storage::disk('public')->delete($relative);
                }
            }
            $image->delete();
        }
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
                'url' => MediaUrl::publicStorage($path),
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
        $normalized = ListingAmenityNormalizer::canonicalizeMany($facilities);
        $facilityIds = collect($normalized)
            ->map(fn ($name) => Facility::firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();

        $listing->facilities()->sync($facilityIds);
        IndexListingJob::dispatch($listing->id);
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }

    private function recordDuplicateAddressAttempt($user, ?string $addressKey): void
    {
        if (! $user || ! $addressKey) {
            return;
        }

        $exists = Listing::where('owner_id', $user->id)
            ->where('address_key', $addressKey)
            ->exists();

        if (! $exists) {
            return;
        }

        $settings = config('security.fraud.signals.duplicate_address_attempt', []);
        $weight = (int) ($settings['weight'] ?? 15);
        $cooldown = (int) ($settings['cooldown_minutes'] ?? 60);

        $this->fraudSignals->recordSignal(
            $user,
            'duplicate_address_attempt',
            $weight,
            ['address_key' => $addressKey],
            $cooldown
        );
    }

    private function isAdmin($user): bool
    {
        return $this->userHasRole($user, 'admin');
    }
}
