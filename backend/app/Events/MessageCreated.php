<?php

namespace App\Events;

use App\Http\Resources\ChatAttachmentResource;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversation.'.$this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing('attachments');

        return [
            'message' => [
                'id' => $message->id,
                'conversationId' => $message->conversation_id,
                'senderId' => $message->sender_id,
                'body' => $message->body,
                'text' => $message->body,
                'time' => optional($message->created_at)->format('H:i'),
                'createdAt' => optional($message->created_at)->toISOString(),
                'attachments' => ChatAttachmentResource::collection($message->attachments)->resolve(),
            ],
        ];
    }
}
