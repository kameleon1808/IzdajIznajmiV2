<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $target = $this->target_summary ?? null;

        return [
            'id' => $this->id,
            'type' => $this->mapType($this->target_type),
            'status' => $this->status,
            'reason' => $this->reason,
            'details' => $this->details,
            'resolution' => $this->resolution,
            'createdAt' => optional($this->created_at)->toISOString(),
            'reviewedAt' => optional($this->reviewed_at)->toISOString(),
            'reporter' => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->full_name ?? $this->reporter?->name,
            ],
            'target' => $target,
            'totalReports' => (int) ($this->total_reports ?? 1),
        ];
    }

    private function mapType(string $class): string
    {
        return match ($class) {
            \App\Models\Rating::class => 'rating',
            \App\Models\Message::class => 'message',
            \App\Models\Listing::class => 'listing',
            default => 'other',
        };
    }
}
