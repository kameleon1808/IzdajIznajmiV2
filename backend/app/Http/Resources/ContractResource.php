<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'templateKey' => $this->template_key,
            'status' => $this->status,
            'contractHash' => $this->contract_hash,
            'pdfUrl' => $this->pdfUrl(),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'signatures' => SignatureResource::collection($this->whenLoaded('signatures')),
        ];
    }

    private function pdfUrl(): string
    {
        return route('contracts.pdf', ['contract' => $this->id]);
    }
}
