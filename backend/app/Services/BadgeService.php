<?php

namespace App\Services;

use App\Models\LandlordMetric;
use App\Models\User;

class BadgeService
{
    public const BADGE_TOP_LANDLORD = 'top_landlord';

    /**
     * @return array<int, string>
     */
    public function badgesFor(User $landlord, ?LandlordMetric $metrics = null): array
    {
        if ($landlord->is_suspicious) {
            return [];
        }

        $override = $this->normalizeOverride($landlord->badge_override_json);
        if ($override !== null && array_key_exists(self::BADGE_TOP_LANDLORD, $override)) {
            return $override[self::BADGE_TOP_LANDLORD] ? [self::BADGE_TOP_LANDLORD] : [];
        }

        $metrics = $metrics ?? LandlordMetric::where('landlord_id', $landlord->id)->first();
        if (!$metrics) {
            return [];
        }

        $avgRating = $metrics->avg_rating_30d ?? $metrics->all_time_avg_rating;
        $meetsCriteria = ($metrics->ratings_count ?? 0) >= 5
            && $avgRating !== null
            && (float) $avgRating >= 4.5
            && $metrics->median_response_time_minutes !== null
            && $metrics->median_response_time_minutes <= 360
            && ($metrics->completed_transactions_count ?? 0) >= 3;

        return $meetsCriteria ? [self::BADGE_TOP_LANDLORD] : [];
    }

    /**
     * @return array<string, bool>|null
     */
    private function normalizeOverride(mixed $override): ?array
    {
        if (is_array($override)) {
            return $override;
        }

        return null;
    }
}
