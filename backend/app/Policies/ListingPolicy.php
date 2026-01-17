<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->status === 'published') {
            return true;
        }

        if ($user && ($user->role === 'admin' || $listing->owner_id === $user->id)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Listing $listing): bool
    {
        if ($listing->status === 'archived' && $user->role !== 'admin') {
            return false;
        }
        return $user->role === 'admin' || $listing->owner_id === $user->id;
    }
}
