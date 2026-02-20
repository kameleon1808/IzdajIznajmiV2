<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViewingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $slot = $this->whenLoaded('slot', fn () => $this->slot);
        $listing = $this->whenLoaded('listing', fn () => $this->listing);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'message' => $this->message,
            'cancelledBy' => $this->cancelled_by,
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'scheduledAt' => optional($this->scheduled_at)->toIso8601String(),
            'slot' => $slot ? $this->formatSlot($slot) : null,
            'listing' => $listing ? $this->formatListing($listing) : null,
            'participants' => [
                'seekerId' => $this->seeker_id,
                'landlordId' => $this->landlord_id,
            ],
        ];
    }

    private function formatSlot($slot): array
    {
        return [
            'id' => $slot->id,
            'startsAt' => $slot->starts_at?->toIso8601String(),
            'endsAt' => $slot->ends_at?->toIso8601String(),
            'capacity' => $slot->capacity,
            'isActive' => (bool) $slot->is_active,
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
