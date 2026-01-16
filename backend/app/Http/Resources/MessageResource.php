<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;
        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'from' => $this->sender_id === $authId ? 'me' : 'them',
            'text' => $this->body,
            'time' => optional($this->created_at)->format('H:i'),
        ];
    }
}
