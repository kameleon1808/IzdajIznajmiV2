<?php

namespace App\Console\Commands;

use App\Jobs\GeocodeListingJob;
use App\Models\Listing;
use Illuminate\Console\Command;

class GeocodeListingsCommand extends Command
{
    protected $signature = 'listings:geocode {--missing : Only geocode listings without coordinates}';

    protected $description = 'Backfill or refresh listing geocodes.';

    public function handle(): int
    {
        $onlyMissing = $this->option('missing');
        $query = Listing::query();

        if ($onlyMissing) {
            $query->where(function ($builder) {
                $builder->whereNull('lat')
                    ->orWhereNull('lng')
                    ->orWhereNull('geocoded_at');
            });
        }

        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($listings) use (&$count, $onlyMissing) {
            foreach ($listings as $listing) {
                $force = $onlyMissing ? false : true;
                GeocodeListingJob::dispatchSync($listing->id, $force);
                $count++;
            }
        });

        $this->info("Processed geocoding for {$count} listing(s).");

        return self::SUCCESS;
    }
}
