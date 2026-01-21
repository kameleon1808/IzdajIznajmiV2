<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ViewingSlot;

class ViewingSlotPolicy
{
    public function update(User $user, ViewingSlot $slot): bool
    {
        return $this->isOwnerOrAdmin($user, $slot);
    }

    public function delete(User $user, ViewingSlot $slot): bool
    {
        return $this->isOwnerOrAdmin($user, $slot);
    }

    private function isOwnerOrAdmin(User $user, ViewingSlot $slot): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';

        return $isAdmin || $slot->landlord_id === $user->id;
    }
}
