<?php

namespace Database\Seeders;

use App\Models\LandlordMetric;
use App\Models\User;
use Illuminate\Database\Seeder;

class LandlordMetricsSeeder extends Seeder
{
    public function run(): void
    {
        $landlords = User::where('role', 'landlord')->orderBy('id')->get();

        foreach ($landlords as $index => $landlord) {
            $isTop = $index < 5;
            LandlordMetric::updateOrCreate(
                ['landlord_id' => $landlord->id],
                [
                    'avg_rating_30d' => $isTop ? 4.8 : 4.2,
                    'all_time_avg_rating' => $isTop ? 4.7 : 4.1,
                    'ratings_count' => $isTop ? 12 : 3,
                    'median_response_time_minutes' => $isTop ? 45 : 180,
                    'completed_transactions_count' => $isTop ? 4 + $index : $index,
                ]
            );
        }
    }
}
