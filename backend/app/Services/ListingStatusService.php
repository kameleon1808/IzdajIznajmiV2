<?php

namespace App\Services;

use App\Models\Listing;
use Carbon\Carbon;

class ListingStatusService
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_RENTED = 'rented';

    public const STATUS_EXPIRED = 'expired';

    public function allowedStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_ARCHIVED,
            self::STATUS_RENTED,
            self::STATUS_EXPIRED,
        ];
    }

    public function markActive(Listing $listing): Listing
    {
        $this->assertTransition($listing->status, self::STATUS_ACTIVE);

        $listing->fill([
            'status' => self::STATUS_ACTIVE,
            'published_at' => Carbon::now(),
            'archived_at' => null,
            'expired_at' => null,
        ])->save();

        return $listing;
    }

    public function markPaused(Listing $listing): Listing
    {
        $this->assertTransition($listing->status, self::STATUS_PAUSED);

        $listing->fill([
            'status' => self::STATUS_PAUSED,
            'published_at' => null,
        ])->save();

        return $listing;
    }

    public function markDraft(Listing $listing): Listing
    {
        $this->assertTransition($listing->status, self::STATUS_DRAFT);

        $listing->fill([
            'status' => self::STATUS_DRAFT,
            'published_at' => null,
            'archived_at' => null,
            'expired_at' => null,
        ])->save();

        return $listing;
    }

    public function markArchived(Listing $listing): Listing
    {
        $this->assertTransition($listing->status, self::STATUS_ARCHIVED);

        $listing->fill([
            'status' => self::STATUS_ARCHIVED,
            'archived_at' => Carbon::now(),
        ])->save();

        return $listing;
    }

    public function markRented(Listing $listing): Listing
    {
        $this->assertTransition($listing->status, self::STATUS_RENTED);

        $listing->fill([
            'status' => self::STATUS_RENTED,
            'published_at' => null,
            'expired_at' => null,
        ])->save();

        return $listing;
    }

    public function markExpired(Listing $listing): Listing
    {
        $this->assertTransition($listing->status, self::STATUS_EXPIRED);

        $listing->fill([
            'status' => self::STATUS_EXPIRED,
            'expired_at' => Carbon::now(),
            'published_at' => null,
        ])->save();

        return $listing;
    }

    public function canTransition(string $from, string $to): bool
    {
        $map = [
            self::STATUS_DRAFT => [self::STATUS_ACTIVE, self::STATUS_ARCHIVED, self::STATUS_DRAFT],
            self::STATUS_ACTIVE => [self::STATUS_PAUSED, self::STATUS_ARCHIVED, self::STATUS_RENTED, self::STATUS_EXPIRED],
            self::STATUS_PAUSED => [self::STATUS_ACTIVE, self::STATUS_ARCHIVED, self::STATUS_DRAFT, self::STATUS_RENTED],
            self::STATUS_ARCHIVED => [self::STATUS_DRAFT],
            self::STATUS_RENTED => [self::STATUS_ACTIVE, self::STATUS_ARCHIVED],
            self::STATUS_EXPIRED => [self::STATUS_ACTIVE, self::STATUS_ARCHIVED, self::STATUS_DRAFT],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function assertTransition(string $from, string $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new \RuntimeException("Cannot transition listing status from {$from} to {$to}");
        }
    }
}
