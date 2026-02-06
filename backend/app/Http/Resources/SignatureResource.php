<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'role' => $this->role,
            'signedAt' => optional($this->signed_at)->toIso8601String(),
            'signatureMethod' => $this->signature_method,
            'signatureData' => $this->signature_data,
        ];
    }
}
