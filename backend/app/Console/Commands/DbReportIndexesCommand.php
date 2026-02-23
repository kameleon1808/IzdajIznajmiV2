<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DbReportIndexesCommand extends Command
{
    protected $signature = 'db:report-indexes';

    protected $description = 'Report presence of recommended production-readiness indexes';

    public function handle(): int
    {
        $checks = [
            [
                'label' => 'Listings search composite (status, city, price_per_month, rooms, expired_at)',
                'table' => 'listings',
                'index' => 'idx_listings_status_city_price_rooms_expired',
            ],
            [
                'label' => 'Listings expiry (status, expired_at)',
                'table' => 'listings',
                'index' => 'idx_listings_status_expired_at',
            ],
            [
                'label' => 'Saved search matches unique (saved_search_id, listing_id)',
                'table' => 'saved_search_matches',
                'index' => ['saved_search_id', 'listing_id'],
                'type' => 'unique',
            ],
            [
                'label' => 'Notifications by read status (user_id, read_at)',
                'table' => 'notifications',
                'index' => 'idx_notifications_user_read_at',
            ],
            [
                'label' => 'Messages timeline (conversation_id, created_at)',
                'table' => 'messages',
                'index' => 'idx_messages_conversation_created_at',
            ],
            [
                'label' => 'Transactions listing status (listing_id, status)',
                'table' => 'rental_transactions',
                'index' => 'idx_rental_transactions_listing_status',
            ],
        ];

        $missing = 0;
        $this->line('Index report:');
        foreach ($checks as $check) {
            $exists = Schema::hasIndex($check['table'], $check['index'], $check['type'] ?? null);
            $mark = $exists ? 'OK' : 'MISSING';
            $this->line(sprintf('- [%s] %s', $mark, $check['label']));
            if (! $exists) {
                $missing++;
            }
        }

        if ($missing > 0) {
            $this->warn(sprintf('Missing %d recommended index(es).', $missing));

            return self::FAILURE;
        }

        $this->info('All recommended indexes are present.');

        return self::SUCCESS;
    }
}
