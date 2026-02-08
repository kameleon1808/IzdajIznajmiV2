<?php

namespace App\Http\Resources;

use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stats = $this->ratingsReceived()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();
        $recent = $this->ratingsReceived()
            ->with(['rater:id,name,full_name', 'listing:id,title'])
            ->latest()
            ->limit(5)
            ->get();

        return [
            'id' => $this->id,
            'fullName' => $this->full_name ?? $this->name,
            'joinedAt' => optional($this->created_at)->toISOString(),
            'badges' => app(BadgeService::class)->badgesFor($this->resource, $this->landlordMetric),
            'verifications' => [
                'email' => (bool) $this->email_verified,
                'phone' => (bool) $this->phone_verified,
                'address' => (bool) $this->address_verified,
            ],
            'landlordVerification' => [
                'status' => $this->landlord_verification_status ?? 'none',
                'verifiedAt' => optional($this->landlord_verified_at)->toISOString(),
            ],
            'ratingStats' => [
                'average' => $stats?->avg_rating ? round((float) $stats->avg_rating, 1) : 0,
                'total' => (int) ($stats?->total ?? 0),
                'breakdown' => [],
            ],
            'recentRatings' => $recent->map(function ($rating) {
                return [
                    'raterName' => $rating->rater?->full_name ?? $rating->rater?->name,
                    'rating' => (int) $rating->rating,
                    'comment' => $rating->comment,
                    'createdAt' => optional($rating->created_at)->toISOString(),
                    'listingTitle' => $rating->listing?->title,
                ];
            }),
        ];
    }
}
