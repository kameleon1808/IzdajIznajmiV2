<?php

namespace App\Console\Commands;

use App\Jobs\IndexListingJob;
use App\Models\Listing;
use App\Services\ListingStatusService;
use App\Services\Search\SearchDriver;
use Illuminate\Console\Command;

class SearchListingsSyncMissingCommand extends Command
{
    protected $signature = 'search:listings:sync-missing {--chunk=500 : Chunk size for listing IDs}';

    protected $description = 'Sync active listings into the search index (best-effort)';

    public function handle(SearchDriver $driver): int
    {
        if (config('search.driver', 'sql') !== 'meili') {
            $this->warn('Search driver is sql; sync skipped.');

            return Command::SUCCESS;
        }

        $driver->configureIndex();

        $chunk = (int) $this->option('chunk');
        $chunk = max(50, $chunk);
        $count = 0;

        Listing::query()
            ->where('status', ListingStatusService::STATUS_ACTIVE)
            ->select('id')
            ->chunkById($chunk, function ($listings) use (&$count) {
                foreach ($listings as $listing) {
                    IndexListingJob::dispatch($listing->id);
                    $count++;
                }
            });

        $this->info("Dispatched {$count} listing(s) for indexing.");

        return Command::SUCCESS;
    }
}
