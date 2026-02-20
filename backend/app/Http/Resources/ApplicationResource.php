<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->resource->relationLoaded('listing') ? $this->listing : null;
        $hasCompletedTransaction = (bool) ($this->resource->has_completed_transaction ?? false);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'message' => $this->message,
            'createdAt' => optional($this->created_at)->toISOString(),
            'listing' => $listing ? $this->formatListing($listing) : null,
            'hasCompletedTransaction' => $hasCompletedTransaction,
            'participants' => [
                'seekerId' => $this->seeker_id,
                'landlordId' => $this->landlord_id,
            ],
        ];
    }

    private function formatListing(Listing $listing): array
    {
        $cover = MediaUrl::normalize($listing->cover_image);
        $firstImage = $listing->relationLoaded('images')
            ? MediaUrl::normalize($listing->images->sortBy('sort_order')->first()?->url)
            : null;

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'city' => $listing->city,
            'pricePerNight' => $listing->price_per_night,
            'coverImage' => $cover ?: $firstImage,
            'status' => $listing->status,
        ];
    }
}
