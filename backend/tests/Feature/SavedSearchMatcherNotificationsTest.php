<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\SavedSearch;
use App\Models\SavedSearchMatch;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SavedSearchMatcherNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_matcher_creates_matches_and_notifies_once(): void
    {
        Cache::forget('saved-searches:last-run-at');
        Cache::lock('saved-search-matcher')->forceRelease();

        $user = User::factory()->create();

        $listing = Listing::factory()->create([
            'status' => ListingStatusService::STATUS_ACTIVE,
            'category' => 'villa',
            'published_at' => now(),
            'created_at' => now(),
        ]);

        $savedSearch = SavedSearch::create([
            'user_id' => $user->id,
            'name' => 'Villa Watch',
            'filters' => ['category' => 'villa'],
            'alerts_enabled' => true,
            'frequency' => 'instant',
        ]);

        $this->artisan('saved-searches:match')->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('saved_search_matches', [
            'saved_search_id' => $savedSearch->id,
            'listing_id' => $listing->id,
        ]);

        $this->assertSame(1, Notification::where('user_id', $user->id)
            ->where('type', Notification::TYPE_LISTING_NEW_MATCH)
            ->count());

        $this->artisan('saved-searches:match')->assertExitCode(Command::SUCCESS);

        $this->assertSame(1, SavedSearchMatch::count());
        $this->assertSame(1, Notification::where('user_id', $user->id)
            ->where('type', Notification::TYPE_LISTING_NEW_MATCH)
            ->count());
    }
}
