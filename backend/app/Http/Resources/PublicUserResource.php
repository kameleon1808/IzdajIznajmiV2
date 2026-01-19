<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->full_name ?? $this->name,
            'joinedAt' => optional($this->created_at)->toISOString(),
            'verifications' => [
                'email' => (bool) $this->email_verified,
                'phone' => (bool) $this->phone_verified,
                'address' => (bool) $this->address_verified,
            ],
            'ratingStats' => [
                'average' => 0,
                'total' => 0,
                'breakdown' => [],
            ],
            'recentRatings' => [],
        ];
    }
}
