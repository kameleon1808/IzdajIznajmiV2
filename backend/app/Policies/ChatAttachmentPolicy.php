<?php

namespace App\Policies;

use App\Models\ChatAttachment;
use App\Models\User;

class ChatAttachmentPolicy
{
    /**
     * Whether the user may view (download / stream) the attachment.
     *
     * The user must be a participant in the conversation that owns the attachment.
     * This covers both the original file and the thumbnail.
     */
    public function view(User $user, ChatAttachment $attachment): bool
    {
        return $attachment->conversation !== null
            && $attachment->conversation->isParticipant($user);
    }
}
