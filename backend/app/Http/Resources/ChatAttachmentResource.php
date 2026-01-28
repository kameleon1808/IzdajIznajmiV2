<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'url' => route('chat.attachments.show', ['attachment' => $this->id], false),
            'thumbUrl' => $this->path_thumb
                ? route('chat.attachments.thumb', ['attachment' => $this->id], false)
                : null,
        ];
    }
}
