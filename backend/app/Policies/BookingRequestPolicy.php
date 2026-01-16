<?php

namespace App\Policies;

use App\Models\BookingRequest;
use App\Models\User;

class BookingRequestPolicy
{
    public function updateStatus(User $user, BookingRequest $bookingRequest, string $status): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($status === 'cancelled') {
            return $bookingRequest->tenant_id === $user->id && $bookingRequest->status === 'pending';
        }

        if (in_array($status, ['accepted', 'rejected'], true)) {
            return $bookingRequest->landlord_id === $user->id && $bookingRequest->status === 'pending';
        }

        return false;
    }
}
