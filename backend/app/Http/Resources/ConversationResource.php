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
        $listing = $this->resource->relationLoaded('listing') ? $this->listing : null;
        $lastMessage = $this->whenLoaded('messages', fn () => $this->messages->sortByDesc('created_at')->first());

        return [
            'id' => $this->id,
            'listingId' => $this->listing_id,
            'listingTitle' => $listing?->title,
            'listingCity' => $listing?->city,
            'listingCoverImage' => $listing?->cover_image ?? ($listing?->relationLoaded('images') ? $listing->images->sortBy('sort_order')->first()?->url : null),
            'userName' => $participant?->name ?? 'Guest',
            'avatarUrl' => null,
            'lastMessage' => $lastMessage?->body ?? 'Start chatting',
            'time' => optional($lastMessage?->created_at ?? $this->created_at)->toISOString(),
            'unreadCount' => $this->unread_count ?? 0,
            'online' => false,
            'participants' => [
                'tenantId' => $this->tenant_id,
                'landlordId' => $this->landlord_id,
            ],
        ];
    }
}
