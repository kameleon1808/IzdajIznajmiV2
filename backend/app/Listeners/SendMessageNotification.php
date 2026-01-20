<?php

namespace App\Listeners;

use App\Events\MessageCreated;
use App\Models\Notification;
use App\Services\NotificationService;

class SendMessageNotification
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(MessageCreated $event): void
    {
        $message = $event->message->loadMissing('conversation.listing', 'sender');
        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        $listing = $conversation->listing;

        // Determine recipient: the other participant in the conversation
        $recipientId = $message->sender_id === $conversation->tenant_id
            ? $conversation->landlord_id
            : $conversation->tenant_id;

        if (! $recipientId) {
            return;
        }

        $recipient = $conversation->tenant_id === $recipientId
            ? $conversation->tenant
            : $conversation->landlord;

        if (! $recipient) {
            return;
        }

        $this->notifications->createNotification($recipient, Notification::TYPE_MESSAGE_RECEIVED, [
            'title' => $listing ? sprintf('New message about "%s"', $listing->title) : 'New message received',
            'body' => mb_strimwidth($message->body, 0, 120, '...'),
            'data' => [
                'conversation_id' => $conversation->id,
                'listing_id' => $listing?->id,
                'sender_id' => $message->sender_id,
            ],
            'url' => sprintf('/chat?conversationId=%d', $conversation->id),
        ]);
    }
}

