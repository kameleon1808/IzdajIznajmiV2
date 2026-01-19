<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListingAddressGuardService
{
    public function normalizeAddressKey(?string $address, ?string $city, ?string $country): ?string
    {
        $parts = array_filter([$address, $city, $country], fn ($part) => filled($part));
        if (empty($parts)) {
            return null;
        }

        $normalized = Str::of(implode(' ', $parts))
            ->lower()
            ->replaceMatches('/[\\p{P}\\p{S}]+/u', ' ')
            ->replaceMatches('/\\s+/', ' ')
            ->trim()
            ->toString();

        return $normalized ?: null;
    }

    /**
     * @return string[] warnings
     */
    public function guardActiveAddress(Listing $listing, ?string $addressKey): array
    {
        if (!$addressKey) {
            return [];
        }

        $matches = Listing::query()
            ->where('address_key', $addressKey)
            ->where('status', ListingStatusService::STATUS_ACTIVE)
            ->when($listing->exists, fn ($query) => $query->where('id', '!=', $listing->id))
            ->get();

        $this->blockIfSameOwnerActive($listing, $matches);

        $otherOwner = $matches->first(fn ($row) => (int) $row->owner_id !== (int) $listing->owner_id);
        if ($otherOwner) {
            return ['Another landlord already has an active listing at this address.'];
        }

        return [];
    }

    private function blockIfSameOwnerActive(Listing $listing, Collection $matches): void
    {
        $sameOwner = $matches->first(fn ($row) => (int) $row->owner_id === (int) $listing->owner_id);
        if ($sameOwner) {
            throw new HttpResponseException(
                response()->json(
                    ['message' => 'You already have an active listing at this address.'],
                    409
                )
            );
        }
    }
}
