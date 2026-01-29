<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'status' => $this->status,
            'submittedAt' => optional($this->submitted_at)->toISOString(),
            'reviewedAt' => optional($this->reviewed_at)->toISOString(),
            'reviewerId' => $this->reviewer_id,
            'reviewerNote' => $this->reviewer_note,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'fullName' => $this->user?->full_name ?? $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer?->id,
                    'fullName' => $this->reviewer?->full_name ?? $this->reviewer?->name,
                ];
            }),
            'documents' => KycDocumentResource::collection($this->whenLoaded('documents')),
            'createdAt' => optional($this->created_at)->toISOString(),
            'updatedAt' => optional($this->updated_at)->toISOString(),
        ];
    }
}
