<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\RentalTransaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionEligibilityService
{
    public function canRate(int $seekerId, int $landlordId, int $listingId): bool
    {
        return $this->eligibleTransactions($seekerId, $landlordId)
            ->where('listing_id', $listingId)
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    public function eligibleListingIds(int $seekerId, int $landlordId): array
    {
        return $this->eligibleTransactions($seekerId, $landlordId)
            ->distinct()
            ->pluck('listing_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function latestEligibleTransaction(int $seekerId, int $landlordId, int $listingId): ?RentalTransaction
    {
        return $this->eligibleTransactions($seekerId, $landlordId)
            ->where('listing_id', $listingId)
            ->latest('created_at')
            ->first();
    }

    private function eligibleTransactions(int $seekerId, int $landlordId): Builder
    {
        return RentalTransaction::query()
            ->where('seeker_id', $seekerId)
            ->where('landlord_id', $landlordId)
            ->where(function (Builder $query) {
                $query->where('status', RentalTransaction::STATUS_COMPLETED)
                    ->orWhere(function (Builder $inner) {
                        $inner->where('status', RentalTransaction::STATUS_MOVE_IN_CONFIRMED)
                            ->whereHas('contracts', function (Builder $contracts) {
                                $contracts->where('status', Contract::STATUS_FINAL);
                            });
                    });
            });
    }
}
