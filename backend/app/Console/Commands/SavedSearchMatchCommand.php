<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\SavedSearch;
use App\Models\SavedSearchMatch;
use App\Services\ListingSearchService;
use App\Services\ListingStatusService;
use App\Services\StructuredLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SavedSearchMatchCommand extends Command
{
    protected $signature = 'saved-searches:match {--timeout=600 : Lock timeout in seconds}';

    protected $description = 'Match saved searches against new active listings and notify users';

    public function handle(StructuredLogger $log, ListingSearchService $searchService): int
    {
        // Use configured lock store (falls back to default cache store) so tests and prod stay consistent.
        $storeName = Config::get('cache.locks.store') ?? Config::get('cache.default', 'file');
        $cache = Cache::store($storeName);
        $owner = uniqid('ssm-', true);

        $lock = $cache->lock('saved-search-matcher', (int) $this->option('timeout'), $owner);

        if (! $lock->get()) {
            $this->warn('Saved search matcher is already running.');
            $log->warning('saved_search_match_skipped', ['reason' => 'lock_exists']);

            return self::FAILURE;
        }

        $this->line("Lock acquired using store [{$storeName}] owner [{$owner}]");

        $log->info('saved_search_match_started', ['lock_ttl' => $this->option('timeout'), 'store' => $storeName]);

        try {
            $since = $this->resolveSince($cache);
            $this->info(sprintf('Matching saved searches for listings since %s', $since->toDateTimeString()));

            $newListingIds = Listing::query()
                ->where('status', ListingStatusService::STATUS_ACTIVE)
                ->where(function ($query) use ($since) {
                    $query->where('created_at', '>=', $since)
                        ->orWhere('published_at', '>=', $since);
                })
                ->pluck('id')
                ->all();

            $savedSearches = SavedSearch::where('alerts_enabled', true)->get();

            foreach ($savedSearches as $savedSearch) {
                $this->processSavedSearch($savedSearch, $newListingIds, $searchService, $log);
            }

            $cache->put($this->lastRunCacheKey(), now()->toIso8601String(), now()->addDays(7));
            $this->info('Saved search matcher completed.');
        } finally {
            $lock->release();
            $log->info('saved_search_match_finished');
        }

        return self::SUCCESS;
    }

    private function processSavedSearch(SavedSearch $savedSearch, array $newListingIds, ListingSearchService $searchService, StructuredLogger $log): void
    {
        if (! empty($newListingIds)) {
            $filters = $savedSearch->filters ?? [];
            $mapMode = (bool) ($filters['mapMode'] ?? false);

            $query = $searchService->baseQuery()->select('listings.id');
            $searchService->applyFilters($query, $filters, $mapMode);
            $query->whereIn('listings.id', $newListingIds);

            $matchingIds = $query->pluck('listings.id')->all();

            if (! empty($matchingIds)) {
                $now = now();
                $rows = array_map(fn ($listingId) => [
                    'saved_search_id' => $savedSearch->id,
                    'listing_id' => $listingId,
                    'matched_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $matchingIds);

                SavedSearchMatch::insertOrIgnore($rows);
            }
        }

        if (! $this->frequencyAllows($savedSearch)) {
            return;
        }

        $newMatchCount = $this->countMatchesSince($savedSearch, $savedSearch->last_alerted_at);

        if ($newMatchCount === 0) {
            return;
        }

        DB::transaction(function () use ($savedSearch, $newMatchCount, $log) {
            $title = $savedSearch->name
                ? sprintf('New listings for "%s"', $savedSearch->name)
                : 'New listings for your saved search';
            $body = sprintf('%d new listing%s matched your saved search.', $newMatchCount, $newMatchCount === 1 ? '' : 's');

            Notification::create([
                'user_id' => $savedSearch->user_id,
                'type' => Notification::TYPE_LISTING_NEW_MATCH,
                'title' => $title,
                'body' => $body,
                'data' => [
                    'savedSearchId' => $savedSearch->id,
                    'matchCount' => $newMatchCount,
                ],
                'url' => "/search?savedSearchId={$savedSearch->id}",
                'is_read' => false,
            ]);

            $savedSearch->forceFill(['last_alerted_at' => now()])->save();
            $log->info('saved_search_notified', [
                'saved_search_id' => $savedSearch->id,
                'match_count' => $newMatchCount,
            ]);
        });
    }

    private function resolveSince($cache): Carbon
    {
        $lastRun = $cache->get($this->lastRunCacheKey());

        if ($lastRun) {
            return Carbon::parse($lastRun);
        }

        return now()->subDay();
    }

    private function lastRunCacheKey(): string
    {
        return 'saved-searches:last-run-at';
    }

    private function frequencyAllows(SavedSearch $savedSearch): bool
    {
        $lastAlerted = $savedSearch->last_alerted_at;
        if (! $lastAlerted) {
            return true;
        }

        return match ($savedSearch->frequency) {
            'daily' => $lastAlerted->lte(now()->subDay()),
            'weekly' => $lastAlerted->lte(now()->subWeek()),
            default => true,
        };
    }

    private function countMatchesSince(SavedSearch $savedSearch, ?Carbon $since): int
    {
        $query = SavedSearchMatch::where('saved_search_id', $savedSearch->id);

        if ($since) {
            $query->where('matched_at', '>', $since);
        }

        return (int) $query->count();
    }
}
