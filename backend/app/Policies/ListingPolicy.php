<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->status === 'active') {
            return true;
        }

        $isAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin');
        if ($user && ($isAdmin || $listing->owner_id === $user->id)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Listing $listing): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        if (in_array($listing->status, ['archived'], true) && !$isAdmin) {
            return false;
        }
        return $isAdmin || $listing->owner_id === $user->id;
    }

    public function updateLocation(User $user, Listing $listing): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';

        return $isAdmin || $listing->owner_id === $user->id;
    }
}
