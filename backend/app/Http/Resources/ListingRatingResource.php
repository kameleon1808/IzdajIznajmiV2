<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listingId' => $this->listing_id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'createdAt' => optional($this->created_at)->toISOString(),
            'seeker' => $this->whenLoaded('seeker', function () {
                return [
                    'id' => $this->seeker?->id,
                    'name' => $this->seeker?->full_name ?? $this->seeker?->name,
                ];
            }),
            'listing' => $this->whenLoaded('listing', function () {
                return [
                    'id' => $this->listing?->id,
                    'title' => $this->listing?->title,
                    'city' => $this->listing?->city,
                ];
            }),
            'reportCount' => $this->whenCounted('reports', fn () => $this->reports_count),
        ];
    }
}
