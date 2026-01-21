<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViewingSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listingId' => $this->listing_id,
            'landlordId' => $this->landlord_id,
            'startsAt' => $this->starts_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'isActive' => (bool) $this->is_active,
            'pattern' => $this->pattern,
            'daysOfWeek' => $this->days_of_week,
            'timeFrom' => $this->time_from,
            'timeTo' => $this->time_to,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
