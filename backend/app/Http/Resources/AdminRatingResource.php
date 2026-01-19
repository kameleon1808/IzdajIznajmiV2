<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRatingResource extends JsonResource
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
            'ratee' => [
                'id' => $this->ratee?->id,
                'name' => $this->ratee?->full_name ?? $this->ratee?->name,
            ],
            'ipAddress' => $this->ip_address,
            'userAgent' => $this->user_agent,
            'reportCount' => $this->whenCounted('reports', fn () => $this->reports_count),
            'isReported' => $this->whenCounted('reports', fn () => $this->reports_count > 0),
        ];
    }
}
