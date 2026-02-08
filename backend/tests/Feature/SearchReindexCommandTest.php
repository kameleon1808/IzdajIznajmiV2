<?php

namespace Tests\Feature;

use App\Jobs\IndexListingJob;
use App\Models\Listing;
use App\Services\ListingStatusService;
use App\Services\Search\ListingSearchResult;
use App\Services\Search\SearchDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class SearchReindexCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reindex_command_dispatches_active_listings(): void
    {
        config(['search.driver' => 'meili']);
        Queue::fake();

        $this->app->instance(SearchDriver::class, new class implements SearchDriver
        {
            public function searchListings(array $filters, int $page, int $perPage): ListingSearchResult
            {
                return new ListingSearchResult([], [], []);
            }

            public function suggest(string $query, int $limit): array
            {
                return [];
            }

            public function indexListing(\App\Models\Listing $listing): void {}

            public function removeListing(int $listingId): void {}

            public function configureIndex(): void {}

            public function resetIndex(): void {}
        });

        [$active, $draft] = Listing::withoutEvents(function () {
            $active = Listing::factory()->create(['status' => ListingStatusService::STATUS_ACTIVE]);
            $draft = Listing::factory()->create(['status' => ListingStatusService::STATUS_DRAFT]);

            return [$active, $draft];
        });

        $this->artisan('search:listings:reindex')->assertExitCode(Command::SUCCESS);

        Queue::assertPushed(IndexListingJob::class, fn ($job) => $job->listingId === $active->id);
        Queue::assertNotPushed(IndexListingJob::class, fn ($job) => $job->listingId === $draft->id);
    }
}
