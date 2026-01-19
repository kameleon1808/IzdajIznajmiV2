<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listingId' => $this->listing_id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'createdAt' => optional($this->created_at)->toISOString(),
            'rater' => [
                'id' => $this->rater?->id,
                'name' => $this->rater?->full_name ?? $this->rater?->name,
            ],
            'rateeId' => $this->ratee_id,
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
