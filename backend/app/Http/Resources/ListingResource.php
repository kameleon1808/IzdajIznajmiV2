<?php

namespace App\Http\Resources;

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
            'distanceKm' => $this->when(isset($this->distance_km), fn () => round((float) $this->distance_km, 2)),
            'pricePerNight' => $this->price_per_night,
            'rating' => (float) $this->rating,
            'reviewsCount' => $this->reviews_count,
            'coverImage' => $this->cover_image,
            'images' => $this->imagesSimple(),
            'imagesDetailed' => $this->whenLoaded('images', fn () => $this->images->map(function ($img) {
                return [
                    'url' => $img->url,
                    'sortOrder' => $img->sort_order,
                    'isCover' => (bool) $img->is_cover,
                    'processingStatus' => $img->processing_status ?? 'done',
                    'processingError' => $img->processing_error,
                ];
            })),
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
                return [
                    'id' => $this->owner?->id,
                    'fullName' => $this->owner?->full_name ?? $this->owner?->name,
                ];
            }),
            'createdAt' => optional($this->created_at)->toISOString(),
            'status' => $this->status,
            'publishedAt' => optional($this->published_at)->toISOString(),
            'archivedAt' => optional($this->archived_at)->toISOString(),
            'expiredAt' => optional($this->expired_at)->toISOString(),
            'warnings' => $this->when(isset($this->warnings), fn () => array_values((array) $this->warnings)),
        ];
    }

    private function imagesSimple(): Collection
    {
        if ($this->relationLoaded('images')) {
            return $this->images->where('processing_status', 'done')->pluck('url');
        }
        return $this->images()->where('processing_status', 'done')->pluck('url');
    }
}
