<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ViewingRequest;

class ViewingRequestPolicy
{
    public function updateStatus(User $user, ViewingRequest $request, string $status): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        if ($isAdmin) {
            return true;
        }

        if (in_array($status, [ViewingRequest::STATUS_CONFIRMED, ViewingRequest::STATUS_REJECTED], true)) {
            return $request->landlord_id === $user->id && $request->status === ViewingRequest::STATUS_REQUESTED;
        }

        if ($status === ViewingRequest::STATUS_CANCELLED) {
            $isOwner = $request->landlord_id === $user->id;
            $isSeeker = $request->seeker_id === $user->id;

            return ($isOwner || $isSeeker)
                && in_array($request->status, [ViewingRequest::STATUS_REQUESTED, ViewingRequest::STATUS_CONFIRMED], true);
        }

        return false;
    }
}
