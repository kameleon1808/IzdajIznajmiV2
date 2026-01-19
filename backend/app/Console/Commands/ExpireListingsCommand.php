<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\ListingStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireListingsCommand extends Command
{
    protected $signature = 'listings:expire';

    protected $description = 'Expire listings that have been active for more than 30 days.';

    public function handle(ListingStatusService $statusService): int
    {
        $now = Carbon::now();
        $expirationCutoff = $now->clone()->subDays(30);

        $query = Listing::query()
            ->where('status', ListingStatusService::STATUS_ACTIVE)
            ->where(function ($builder) use ($expirationCutoff) {
                $builder->whereNotNull('published_at')
                    ->where('published_at', '<=', $expirationCutoff)
                    ->orWhere(function ($inner) use ($expirationCutoff) {
                        $inner->whereNull('published_at')->where('created_at', '<=', $expirationCutoff);
                    });
            });

        $count = 0;
        $query->chunkById(200, function ($listings) use ($statusService, &$count) {
            foreach ($listings as $listing) {
                $statusService->markExpired($listing);
                $count++;
            }
        });

        $this->info("Expired {$count} listing(s).");

        return self::SUCCESS;
    }
}
