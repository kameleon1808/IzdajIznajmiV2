<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user && ((method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin');
        $canAccess = $user && ($user->id === $this->user_id || $isAdmin);

        return [
            'id' => $this->id,
            'docType' => $this->doc_type,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'sizeBytes' => (int) $this->size_bytes,
            'avStatus' => $this->av_status ?? 'pending',
            'createdAt' => optional($this->created_at)->toISOString(),
            'downloadUrl' => $canAccess ? route('kyc.documents.show', ['document' => $this->id], false) : null,
        ];
    }
}
