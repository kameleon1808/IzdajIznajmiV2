<?php

namespace App\Policies;

use App\Models\RentalTransaction;
use App\Models\User;

class RentalTransactionPolicy
{
    public function view(User $user, RentalTransaction $transaction): bool
    {
        return $this->isAdmin($user)
            || $transaction->landlord_id === $user->id
            || $transaction->seeker_id === $user->id;
    }

    public function manage(User $user, RentalTransaction $transaction): bool
    {
        return $this->isAdmin($user) || $transaction->landlord_id === $user->id;
    }

    public function pay(User $user, RentalTransaction $transaction): bool
    {
        return $this->isAdmin($user) || $transaction->seeker_id === $user->id;
    }

    private function isAdmin(User $user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
    }
}
