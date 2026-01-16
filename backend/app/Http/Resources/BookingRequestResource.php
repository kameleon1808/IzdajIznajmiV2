<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listingId' => $this->listing_id,
            'tenantId' => $this->tenant_id,
            'landlordId' => $this->landlord_id,
            'startDate' => optional($this->start_date)->toDateString(),
            'endDate' => optional($this->end_date)->toDateString(),
            'guests' => $this->guests,
            'message' => $this->message,
            'status' => $this->status,
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
