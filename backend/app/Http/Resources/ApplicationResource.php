<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Support\MediaUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing = $this->resource->relationLoaded('listing') ? $this->listing : null;
        $hasCompletedTransaction = (bool) ($this->resource->has_completed_transaction ?? false);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'message' => $this->message,
            'createdAt' => optional($this->created_at)->toISOString(),
            'updatedAt' => optional($this->updated_at)->toISOString(),
            'startDate' => optional($this->start_date)->toDateString(),
            'endDate' => optional($this->end_date)->toDateString(),
            'withdrawnAt' => optional($this->withdrawn_at)->toISOString(),
            'currency' => 'EUR',
            'calculatedPrice' => $this->calculatePrice($listing?->price_per_month),
            'listing' => $listing ? $this->formatListing($listing) : null,
            'hasCompletedTransaction' => $hasCompletedTransaction,
            'participants' => [
                'seekerId' => $this->seeker_id,
                'landlordId' => $this->landlord_id,
                'seekerName' => $this->seeker?->full_name ?? $this->seeker?->name,
                'landlordName' => $this->landlord?->full_name ?? $this->landlord?->name,
            ],
        ];
    }

    private function formatListing(Listing $listing): array
    {
        $cover = MediaUrl::normalize($listing->cover_image);
        $firstImage = $listing->relationLoaded('images')
            ? MediaUrl::normalize($listing->images->sortBy('sort_order')->first()?->url)
            : null;

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'city' => $listing->city,
            'pricePerMonth' => $listing->price_per_month,
            'coverImage' => $cover ?: $firstImage,
            'status' => $listing->status,
        ];
    }

    private function calculatePrice(?float $monthlyPrice): ?float
    {
        if (! $monthlyPrice || ! $this->start_date || ! $this->end_date) {
            return null;
        }

        try {
            $start = Carbon::parse($this->start_date)->startOfDay();
            $end = Carbon::parse($this->end_date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        $fullMonths = $start->diffInMonths($end);
        $pivot = $start->copy()->addMonthsNoOverflow($fullMonths);
        $remainingDays = $pivot->diffInDays($end);
        $daysInPivotMonth = max(1, $pivot->daysInMonth);
        $months = $fullMonths + ($remainingDays / $daysInPivotMonth);

        return round($months * $monthlyPrice, 2);
    }
}
