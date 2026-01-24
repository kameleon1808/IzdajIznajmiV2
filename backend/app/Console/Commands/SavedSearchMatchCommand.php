<?php

namespace App\Console\Commands;

use App\Services\StructuredLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SavedSearchMatchCommand extends Command
{
    protected $signature = 'saved-searches:run {--timeout=600 : Lock timeout in seconds} {--hold=3 : Seconds to hold the lock (simulates matcher work)}';

    protected $description = 'Run saved search matcher with a mutex to avoid concurrent runs';

    public function handle(StructuredLogger $log): int
    {
        $storeName = 'file'; // force cross-process lock
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
            // Placeholder: plug actual saved-search matching job/service here.
            $this->info('Saved search matcher executed (stub).');
            sleep((int) $this->option('hold'));
        } finally {
            $lock->release();
            $log->info('saved_search_match_finished');
        }

        return self::SUCCESS;
    }
}
