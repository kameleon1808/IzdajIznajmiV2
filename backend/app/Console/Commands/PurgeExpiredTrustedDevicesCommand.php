<?php

namespace App\Console\Commands;

use App\Models\TrustedDevice;
use Illuminate\Console\Command;

class PurgeExpiredTrustedDevicesCommand extends Command
{
    protected $signature = 'trusted-devices:purge';

    protected $description = 'Delete expired trusted device records';

    public function handle(): int
    {
        $deleted = TrustedDevice::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Purged {$deleted} expired trusted device(s).");

        return self::SUCCESS;
    }
}
