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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'lat' => $this->lat,
            'lng' => $this->lng,
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
            'category' => $this->category,
            'isFavorite' => false,
            'instantBook' => (bool) $this->instant_book,
            'facilities' => $this->whenLoaded('facilities', fn () => $this->facilities->pluck('name')),
            'ownerId' => $this->owner_id,
            'createdAt' => optional($this->created_at)->toISOString(),
            'status' => $this->status,
            'publishedAt' => optional($this->published_at)->toISOString(),
            'archivedAt' => optional($this->archived_at)->toISOString(),
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
