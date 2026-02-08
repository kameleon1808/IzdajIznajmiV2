<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LandlordMetricsService;
use Illuminate\Console\Command;

class RecomputeBadgesCommand extends Command
{
    protected $signature = 'badges:recompute {--landlord_id=}';

    protected $description = 'Recompute landlord metrics used for badges.';

    public function handle(LandlordMetricsService $metricsService): int
    {
        $landlordId = $this->option('landlord_id');
        if ($landlordId) {
            $landlord = User::find($landlordId);
            if (! $landlord) {
                $this->error('Landlord not found.');

                return self::FAILURE;
            }
            $metricsService->recomputeForLandlord($landlord);
            $this->info('Recomputed metrics for landlord '.$landlord->id.'.');

            return self::SUCCESS;
        }

        $count = $metricsService->recomputeAll();
        $this->info("Recomputed metrics for {$count} landlord(s).");

        return self::SUCCESS;
    }
}
