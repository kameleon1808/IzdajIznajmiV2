<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatSpamGuardService
{
    public function assertCanSend(User $sender, Conversation $conversation): void
    {
        if ($sender->id !== $conversation->tenant_id) {
            return;
        }

        $hasLandlordReply = $conversation->messages()
            ->where('sender_id', $conversation->landlord_id)
            ->exists();

        if ($hasLandlordReply) {
            return;
        }

        $seekerMessages = $conversation->messages()
            ->where('sender_id', $conversation->tenant_id)
            ->count();

        if ($seekerMessages >= 3) {
            throw new HttpResponseException(response()->json([
                'message' => 'Please wait for the landlord to reply before sending more messages.',
            ], 429));
        }
    }
}
