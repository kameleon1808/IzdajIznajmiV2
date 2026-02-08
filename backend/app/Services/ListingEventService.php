<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

class ListingEventService
{
    public function recordView(User $user, Listing $listing, array $meta = []): bool
    {
        if (! $this->isSeeker($user)) {
            return false;
        }

        if ((int) $listing->owner_id === (int) $user->id) {
            return false;
        }

        $cutoff = Carbon::now()->subHours(12);
        $exists = ListingEvent::query()
            ->where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->where('event_type', ListingEvent::TYPE_VIEW)
            ->where('created_at', '>=', $cutoff)
            ->exists();

        if ($exists) {
            return false;
        }

        ListingEvent::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'event_type' => ListingEvent::TYPE_VIEW,
            'meta' => empty($meta) ? null : $meta,
        ]);

        return true;
    }

    private function isSeeker(User $user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('seeker')) || $user->role === 'seeker';
    }
}
