<?php

namespace App\Http\Resources;

use App\Services\BadgeService;
use App\Services\TransactionEligibilityService;
use App\Support\MediaUrl;
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
        $viewer = $request->user();
        $eligibleListingIds = [];
        $canRateLandlord = false;
        $canRateListing = false;
        $canRateSeeker = false;

        if ($viewer && $viewer->id !== $this->id) {
            $isSeeker = (method_exists($viewer, 'hasRole') && $viewer->hasRole('seeker')) || $viewer->role === 'seeker';
            $isLandlord = (method_exists($viewer, 'hasRole') && $viewer->hasRole('landlord')) || $viewer->role === 'landlord';
            if ($isSeeker) {
                $eligibleListingIds = app(TransactionEligibilityService::class)->eligibleListingIds($viewer->id, $this->id);
                $canRateLandlord = ! empty($eligibleListingIds);
                $canRateListing = $canRateLandlord;
            } elseif ($isLandlord) {
                $eligibleListingIds = app(TransactionEligibilityService::class)->eligibleListingIds($this->id, $viewer->id);
                $canRateSeeker = ! empty($eligibleListingIds);
            }
        }

        return [
            'id' => $this->id,
            'role' => $this->role,
            'fullName' => $this->full_name ?? $this->name,
            'avatarUrl' => $this->avatar_path ? MediaUrl::publicStorage($this->avatar_path) : null,
            'joinedAt' => optional($this->created_at)->toISOString(),
            'badges' => app(BadgeService::class)->badgesFor($this->resource, $this->landlordMetric),
            'verifications' => [
                'email' => (bool) $this->email_verified,
                'address' => (bool) $this->address_verified,
            ],
            'verification' => [
                'status' => $this->verification_status ?? 'none',
                'verifiedAt' => optional($this->verified_at)->toISOString(),
            ],
            'ratingStats' => [
                'average' => $stats?->avg_rating ? round((float) $stats->avg_rating, 1) : 0,
                'total' => (int) ($stats?->total ?? 0),
                'breakdown' => [],
            ],
            'canRateLandlord' => $canRateLandlord,
            'canRateListing' => $canRateListing,
            'canRateSeeker' => $canRateSeeker,
            'eligibleListingIds' => $eligibleListingIds,
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
