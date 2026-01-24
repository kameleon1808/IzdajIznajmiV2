<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class RatingService
{
    public function assertCanRate(User $rater, Listing $listing, int $rateeId): void
    {
        if ($rater->id === $rateeId) {
            throw new HttpResponseException(response()->json(['message' => 'You cannot rate yourself'], 422));
        }

        if (!$this->isVerified($rater)) {
            throw new HttpResponseException(response()->json(['message' => 'Verify your email, phone, and address to rate'], 422));
        }

        if ($this->hasExistingRating($rater->id, $listing->id, $rateeId)) {
            throw new HttpResponseException(response()->json(['message' => 'You already rated this user for this listing'], 409));
        }

        if ($this->exceedsRateLimit($rater->id)) {
            throw new HttpResponseException(response()->json(['message' => 'Rating limit reached. Try again later.'], 429));
        }

        if (!$this->hasMessagingHistory($rater->id, $rateeId, $listing->id)) {
            throw new HttpResponseException(response()->json(['message' => 'You need a conversation with messages before rating'], 422));
        }
    }

    public function createRating(User $rater, Listing $listing, int $rateeId, int $score, ?string $comment, ?string $ip, ?string $ua): Rating
    {
        $this->assertCanRate($rater, $listing, $rateeId);

        $rating = Rating::create([
            'listing_id' => $listing->id,
            'rater_id' => $rater->id,
            'ratee_id' => $rateeId,
            'rating' => $score,
            'comment' => $comment,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);

        event(new \App\Events\RatingCreated($rating));

        app(\App\Services\StructuredLogger::class)->info('rating_created', [
            'listing_id' => $listing->id,
            'user_id' => $rater->id,
            'ratee_id' => $rateeId,
            'rating_id' => $rating->id,
        ]);

        return $rating;
    }

    private function isVerified(User $user): bool
    {
        return (bool) $user->email_verified && (bool) $user->phone_verified && (bool) $user->address_verified;
    }

    private function hasExistingRating(int $raterId, int $listingId, int $rateeId): bool
    {
        return Rating::where([
            'rater_id' => $raterId,
            'listing_id' => $listingId,
            'ratee_id' => $rateeId,
        ])->exists();
    }

    private function exceedsRateLimit(int $raterId): bool
    {
        $count = Rating::where('rater_id', $raterId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count >= 5;
    }

    private function hasMessagingHistory(int $raterId, int $rateeId, int $listingId): bool
    {
        $conversation = Conversation::where('listing_id', $listingId)
            ->where(function ($query) use ($raterId, $rateeId) {
                $query->where(function ($q) use ($raterId, $rateeId) {
                    $q->where('tenant_id', $raterId)->where('landlord_id', $rateeId);
                })->orWhere(function ($q) use ($raterId, $rateeId) {
                    $q->where('tenant_id', $rateeId)->where('landlord_id', $raterId);
                });
            })
            ->first();

        if (!$conversation) {
            return false;
        }

        return $conversation->messages()->exists();
    }
}
