<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;

class SavedSearchPolicy
{
    public function view(User $user, SavedSearch $savedSearch): bool
    {
        return $this->isOwnerOrAdmin($user, $savedSearch);
    }

    public function update(User $user, SavedSearch $savedSearch): bool
    {
        return $this->isOwnerOrAdmin($user, $savedSearch);
    }

    public function delete(User $user, SavedSearch $savedSearch): bool
    {
        return $this->isOwnerOrAdmin($user, $savedSearch);
    }

    private function isOwnerOrAdmin(User $user, SavedSearch $savedSearch): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';

        return $isAdmin || $savedSearch->user_id === $user->id;
    }
}
