<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function view(?User $user, Listing $listing): bool
    {
        return true;
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->role === 'admin' || $listing->owner_id === $user->id;
    }
}
