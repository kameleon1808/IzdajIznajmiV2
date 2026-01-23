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
        $query = Listing::query()->where('location_source', '!=', 'manual');

        if ($onlyMissing) {
            $query->where(function ($builder) {
                $builder->whereNull('lat')
                    ->orWhereNull('lng')
                    ->orWhereNull('geocoded_at')
                    ->orWhere('lat', '<', -90)
                    ->orWhere('lat', '>', 90)
                    ->orWhere('lng', '<', -180)
                    ->orWhere('lng', '>', 180);
            });
        }

        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($listings) use (&$count, $onlyMissing) {
            foreach ($listings as $listing) {
                if ($listing->location_source === 'manual') {
                    continue;
                }

                $force = $onlyMissing ? false : true;
                GeocodeListingJob::dispatchSync($listing->id, $force);
                $count++;
            }
        });

        $this->info("Processed geocoding for {$count} listing(s).");

        return self::SUCCESS;
    }
}
