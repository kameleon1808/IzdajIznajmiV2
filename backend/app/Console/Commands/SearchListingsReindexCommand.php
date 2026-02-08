<?php

namespace App\Console\Commands;

use App\Jobs\IndexListingJob;
use App\Models\Listing;
use App\Services\ListingStatusService;
use App\Services\Search\SearchDriver;
use Illuminate\Console\Command;

class SearchListingsReindexCommand extends Command
{
    protected $signature = 'search:listings:reindex {--chunk=500 : Chunk size for listing IDs} {--reset : Drop and recreate the index before reindexing}';

    protected $description = 'Reindex active listings into the configured search driver';

    public function handle(SearchDriver $driver): int
    {
        if (config('search.driver', 'sql') !== 'meili') {
            $this->warn('Search driver is sql; reindex skipped.');

            return Command::SUCCESS;
        }

        if ($this->option('reset')) {
            $this->info('Resetting index...');
            $driver->resetIndex();
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
