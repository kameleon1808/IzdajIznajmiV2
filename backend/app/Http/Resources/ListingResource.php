<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('url'), fn () => $this->images()->pluck('url')),
            'description' => $this->description,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'category' => $this->category,
            'isFavorite' => false,
            'instantBook' => (bool) $this->instant_book,
            'facilities' => $this->whenLoaded('facilities', fn () => $this->facilities->pluck('name')),
            'ownerId' => $this->owner_id,
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
