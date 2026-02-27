<?php

namespace App\Policies;

use App\Models\KycDocument;
use App\Models\User;

class KycDocumentPolicy
{
    /**
     * Whether the user may view (download / stream) the KYC document.
     *
     * Allowed for the document owner and for users with the admin role.
     */
    public function view(User $user, KycDocument $document): bool
    {
        return $document->user_id === $user->id || $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin']))
            || ($user->role ?? null) === 'admin';
    }
}
