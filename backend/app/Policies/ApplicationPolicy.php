<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function updateStatus(User $user, Application $application, string $status): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        if ($isAdmin) {
            return true;
        }

        if ($status === Application::STATUS_WITHDRAWN) {
            return $application->seeker_id === $user->id && $application->status === Application::STATUS_SUBMITTED;
        }

        if (in_array($status, [Application::STATUS_ACCEPTED, Application::STATUS_REJECTED], true)) {
            return $application->landlord_id === $user->id && $application->status === Application::STATUS_SUBMITTED;
        }

        return false;
    }
}
