<?php

namespace App\Console\Commands;

use App\Jobs\IndexListingJob;
use App\Models\Listing;
use App\Models\ListingRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncListingRatingsCommand extends Command
{
    protected $signature = 'listings:sync-ratings';

    protected $description = 'Recompute listing rating and reviews_count from listing_ratings table.';

    public function handle(): int
    {
        $stats = ListingRating::select('listing_id', DB::raw('ROUND(AVG(rating), 1) as avg_rating'), DB::raw('COUNT(*) as rating_count'))
            ->groupBy('listing_id')
            ->get();

        $bar = $this->output->createProgressBar($stats->count());
        $bar->start();

        foreach ($stats as $row) {
            Listing::where('id', $row->listing_id)->update([
                'rating' => (float) $row->avg_rating,
                'reviews_count' => (int) $row->rating_count,
            ]);

            IndexListingJob::dispatch((int) $row->listing_id);

            $bar->advance();
        }

        // Zero out listings that have no ratings but a stale rating value
        Listing::whereNotIn('id', $stats->pluck('listing_id'))
            ->where(function ($q) {
                $q->where('rating', '!=', 0)->orWhere('reviews_count', '!=', 0);
            })
            ->update(['rating' => 0.0, 'reviews_count' => 0]);

        $bar->finish();
        $this->newLine();
        $this->info("Synced ratings for {$stats->count()} listings.");

        return self::SUCCESS;
    }
}
