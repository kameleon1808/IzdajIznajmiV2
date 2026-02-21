<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ListingRatingService
{
    public function __construct(private TransactionEligibilityService $eligibility) {}

    public function assertCanRate(User $rater, Listing $listing): void
    {
        if (! $this->isSeeker($rater)) {
            throw new HttpResponseException(response()->json(['message' => 'Only seekers can leave ratings'], 403));
        }

        if ((int) $listing->owner_id === (int) $rater->id) {
            throw new HttpResponseException(response()->json(['message' => 'You cannot rate your own listing'], 422));
        }

        if (! $this->eligibility->canRate($rater->id, (int) $listing->owner_id, $listing->id)) {
            throw new HttpResponseException(response()->json(['message' => 'Completed transaction required to rate'], 403));
        }

        if (! $this->isVerified($rater)) {
            throw new HttpResponseException(response()->json(['message' => 'Verify your email and address to rate'], 422));
        }

        if ($this->hasExistingRating($rater->id, $listing->id)) {
            throw new HttpResponseException(response()->json(['message' => 'You already rated this listing'], 409));
        }

        if ($this->exceedsRateLimit($rater->id)) {
            throw new HttpResponseException(response()->json(['message' => 'Rating limit reached. Try again later.'], 429));
        }
    }

    public function createRating(User $rater, Listing $listing, int $score, ?string $comment, ?string $ip, ?string $ua): ListingRating
    {
        $this->assertCanRate($rater, $listing);

        $transaction = $this->eligibility->latestEligibleTransaction($rater->id, (int) $listing->owner_id, $listing->id);
        if (! $transaction) {
            throw new HttpResponseException(response()->json(['message' => 'Completed transaction required to rate'], 403));
        }

        return ListingRating::create([
            'listing_id' => $listing->id,
            'seeker_id' => $rater->id,
            'transaction_id' => $transaction->id,
            'rating' => $score,
            'comment' => $comment,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }

    private function isVerified(User $user): bool
    {
        return (bool) $user->email_verified && (bool) $user->address_verified;
    }

    private function hasExistingRating(int $seekerId, int $listingId): bool
    {
        return ListingRating::where([
            'seeker_id' => $seekerId,
            'listing_id' => $listingId,
        ])->exists();
    }

    private function exceedsRateLimit(int $seekerId): bool
    {
        $count = ListingRating::where('seeker_id', $seekerId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count >= 5;
    }

    private function isSeeker(User $user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('seeker')) || $user->role === 'seeker';
    }
}
