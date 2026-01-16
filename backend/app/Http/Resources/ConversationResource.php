<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;
        $participant = $authId === $this->tenant_id ? $this->landlord : $this->tenant;
        $lastMessage = $this->whenLoaded('messages', fn () => $this->messages->sortByDesc('created_at')->first());

        return [
            'id' => $this->id,
            'userName' => $participant?->name ?? 'Guest',
            'avatarUrl' => null,
            'lastMessage' => $lastMessage?->body ?? 'Start chatting',
            'time' => optional($lastMessage?->created_at ?? $this->created_at)->format('H:i'),
            'unreadCount' => 0,
            'online' => false,
        ];
    }
}
