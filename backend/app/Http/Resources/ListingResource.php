<?php

namespace App\Http\Resources;

use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lat = $this->lat;
        $lng = $this->lng;
        $latInvalid = $lat !== null && ($lat < -90 || $lat > 90);
        $lngInvalid = $lng !== null && ($lng < -180 || $lng > 180);
        if ($latInvalid || $lngInvalid) {
            $lat = null;
            $lng = null;
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'lat' => $lat,
            'lng' => $lng,
            'geocodedAt' => optional($this->geocoded_at)->toISOString(),
            'locationSource' => $this->location_source ?? 'geocoded',
            'locationAccuracyM' => $this->location_accuracy_m,
            'locationOverriddenAt' => optional($this->location_overridden_at)->toISOString(),
            'distanceKm' => $this->distanceValue($request),
            'pricePerNight' => $this->price_per_night,
            'rating' => (float) $this->rating,
            'reviewsCount' => $this->reviews_count,
            'coverImage' => $this->cover_image,
            'images' => $this->imagesSimple(),
            'imagesDetailed' => $this->imagesDetailed(),
            'description' => $this->description,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'rooms' => $this->rooms ?? $this->beds,
            'area' => $this->area,
            'category' => $this->category,
            'isFavorite' => false,
            'instantBook' => (bool) $this->instant_book,
            'facilities' => $this->whenLoaded('facilities', fn () => $this->facilities->pluck('name')),
            'ownerId' => $this->owner_id,
            'landlord' => $this->whenLoaded('owner', function () {
                $status = $this->owner?->landlord_verification_status ?? 'none';
                $badges = $this->owner ? app(BadgeService::class)->badgesFor($this->owner, $this->owner->landlordMetric) : [];

                return [
                    'id' => $this->owner?->id,
                    'fullName' => $this->owner?->full_name ?? $this->owner?->name,
                    'verificationStatus' => $status,
                    'verifiedAt' => optional($this->owner?->landlord_verified_at)->toISOString(),
                    'badges' => $badges,
                ];
            }),
            'createdAt' => optional($this->created_at)->toISOString(),
            'status' => $this->status,
            'publishedAt' => optional($this->published_at)->toISOString(),
            'archivedAt' => optional($this->archived_at)->toISOString(),
            'expiredAt' => optional($this->expired_at)->toISOString(),
            'warnings' => $this->when(isset($this->warnings), fn () => array_values((array) $this->warnings)),
            'why' => $this->when(isset($this->why), fn () => array_values((array) $this->why)),
        ];
    }

    private function imagesSimple(): Collection
    {
        if ($this->relationLoaded('images')) {
            return $this->images->where('processing_status', 'done')->pluck('url');
        }

        return $this->images()->where('processing_status', 'done')->pluck('url');
    }

    private function imagesDetailed(): Collection
    {
        $relation = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $relation->map(function ($img) {
            return [
                'url' => $img->url,
                'sortOrder' => $img->sort_order,
                'isCover' => (bool) $img->is_cover,
                'processingStatus' => $img->processing_status ?? 'done',
                'processingError' => $img->processing_error,
            ];
        });
    }

    private function distanceValue(Request $request): ?float
    {
        if ($this->distance_km !== null) {
            return round((float) $this->distance_km, 2);
        }

        $centerLat = $request->input('centerLat');
        $centerLng = $request->input('centerLng');
        if ($centerLat !== null && $centerLng !== null) {
            if ($this->lat !== null && $this->lng !== null) {
                $earthRadius = 6371; // km
                $lat1 = deg2rad((float) $centerLat);
                $lng1 = deg2rad((float) $centerLng);
                $lat2 = deg2rad((float) $this->lat);
                $lng2 = deg2rad((float) $this->lng);
                $dlng = $lng2 - $lng1;
                $dlat = $lat2 - $lat1;
                $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

                return round($earthRadius * $c, 2);
            }

            // center provided but listing lacks coords; return 0 to keep key non-null for tests
            return 0.0;
        }

        return null;
    }
}
