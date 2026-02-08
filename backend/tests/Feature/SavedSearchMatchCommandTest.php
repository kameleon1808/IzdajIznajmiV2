<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SavedSearchMatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_search_matcher_honors_mutex(): void
    {
        Cache::lock('saved-search-matcher')->forceRelease();

        $this->artisan('saved-searches:match')->assertExitCode(Command::SUCCESS);

        $lock = Cache::lock('saved-search-matcher', 600);
        $lock->get();

        $this->artisan('saved-searches:match')
            ->expectsOutput('Saved search matcher is already running.')
            ->assertExitCode(Command::FAILURE);

        $lock->release();
    }
}
