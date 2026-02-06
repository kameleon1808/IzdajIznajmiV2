<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->whenLoaded('listing', fn () => $this->listing);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'depositAmount' => $this->deposit_amount !== null ? (float) $this->deposit_amount : null,
            'rentAmount' => $this->rent_amount !== null ? (float) $this->rent_amount : null,
            'currency' => $this->currency,
            'startedAt' => optional($this->started_at)->toIso8601String(),
            'completedAt' => optional($this->completed_at)->toIso8601String(),
            'participants' => [
                'landlordId' => $this->landlord_id,
                'seekerId' => $this->seeker_id,
            ],
            'listing' => $listing ? $this->formatListing($listing) : null,
            'contract' => $this->whenLoaded('latestContract', function () {
                return $this->latestContract ? new ContractResource($this->latestContract) : null;
            }),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function formatListing(Listing $listing): array
    {
        $cover = $listing->cover_image;
        $firstImage = $listing->relationLoaded('images')
            ? $listing->images->sortBy('sort_order')->first()?->url
            : null;

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'address' => $listing->address,
            'city' => $listing->city,
            'coverImage' => $cover ?: $firstImage,
            'status' => $listing->status,
        ];
    }
}
