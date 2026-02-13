<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'isAdmin' => (bool) $this->is_admin,
            'createdAt' => optional($this->created_at)->toISOString(),
            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => $this->author?->id,
                    'name' => $this->author?->full_name ?? $this->author?->name,
                ];
            }),
        ];
    }
}
