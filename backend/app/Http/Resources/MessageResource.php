<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ChatAttachmentResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;
        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'senderId' => $this->sender_id,
            'from' => $this->sender_id === $authId ? 'me' : 'them',
            'body' => $this->body,
            'text' => $this->body,
            'time' => optional($this->created_at)->format('H:i'),
            'createdAt' => optional($this->created_at)->toISOString(),
            'attachments' => ChatAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
