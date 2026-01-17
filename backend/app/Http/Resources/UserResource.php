<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'addressBook' => $this->address_book,
            'emailVerified' => (bool) $this->email_verified,
            'phoneVerified' => (bool) $this->phone_verified,
            'addressVerified' => (bool) $this->address_verified,
            'isSuspicious' => (bool) $this->is_suspicious,
        ];
    }
}
