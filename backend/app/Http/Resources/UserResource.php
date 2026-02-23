<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
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
            'dateOfBirth' => optional($this->date_of_birth)->toDateString(),
            'gender' => $this->gender,
            'residentialAddress' => $this->residential_address,
            'employmentStatus' => $this->employment_status,
            'avatarUrl' => $this->avatar_path ? MediaUrl::publicStorage($this->avatar_path) : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'addressBook' => $this->address_book,
            'emailVerified' => (bool) $this->email_verified,
            'addressVerified' => (bool) $this->address_verified,
            'isSuspicious' => (bool) $this->is_suspicious,
            'mfaEnabled' => (bool) $this->mfa_enabled,
            'mfaConfirmedAt' => optional($this->mfa_confirmed_at)->toISOString(),
            'mfaRequired' => (bool) (config('security.require_mfa_for_admins') && ($this->role === 'admin' || $this->hasRole('admin'))),
            'verificationStatus' => $this->verification_status ?? 'none',
            'verifiedAt' => optional($this->verified_at)->toISOString(),
            'verificationNotes' => $this->verification_notes,
        ];
    }
}
