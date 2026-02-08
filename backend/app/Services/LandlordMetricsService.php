<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\LandlordMetric;
use App\Models\Message;
use App\Models\Rating;
use App\Models\RentalTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class LandlordMetricsService
{
    public function recomputeForLandlord(User $landlord): LandlordMetric
    {
        $ratingsQuery = Rating::query()->where('ratee_id', $landlord->id);
        $ratingsCount = $ratingsQuery->count();
        $allTimeAvg = $ratingsQuery->avg('rating');

        $avg30d = Rating::query()
            ->where('ratee_id', $landlord->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('rating');

        $completedTransactions = RentalTransaction::query()
            ->where('landlord_id', $landlord->id)
            ->where('status', RentalTransaction::STATUS_COMPLETED)
            ->count();

        $medianResponse = $this->medianResponseTimeMinutes($landlord->id);

        return LandlordMetric::updateOrCreate(
            ['landlord_id' => $landlord->id],
            [
                'avg_rating_30d' => $avg30d !== null ? round((float) $avg30d, 2) : null,
                'all_time_avg_rating' => $allTimeAvg !== null ? round((float) $allTimeAvg, 2) : null,
                'ratings_count' => $ratingsCount,
                'median_response_time_minutes' => $medianResponse,
                'completed_transactions_count' => $completedTransactions,
            ]
        );
    }

    public function recomputeAll(): int
    {
        $count = 0;
        User::query()
            ->where('role', 'landlord')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'landlord'))
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $this->recomputeForLandlord($user);
                    $count++;
                }
            });

        return $count;
    }

    private function medianResponseTimeMinutes(int $landlordId): ?int
    {
        $cutoff = now()->subDays(30);

        $conversations = Conversation::query()
            ->where('landlord_id', $landlordId)
            ->get(['id', 'tenant_id', 'landlord_id']);

        if ($conversations->isEmpty()) {
            return null;
        }

        $conversationMap = $conversations->keyBy('id');
        $conversationIds = $conversations->pluck('id')->all();

        $messages = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('created_at', '>=', $cutoff)
            ->orderBy('conversation_id')
            ->orderBy('created_at')
            ->get(['conversation_id', 'sender_id', 'created_at']);

        if ($messages->isEmpty()) {
            return null;
        }

        $responseTimes = [];
        $lastTenantMessage = [];

        foreach ($messages as $message) {
            $conversation = $conversationMap->get($message->conversation_id);
            if (! $conversation) {
                continue;
            }
            $tenantId = (int) $conversation->tenant_id;
            $senderId = (int) $message->sender_id;

            if ($senderId === $tenantId) {
                if (! isset($lastTenantMessage[$message->conversation_id])) {
                    $lastTenantMessage[$message->conversation_id] = $message->created_at instanceof Carbon
                        ? $message->created_at
                        : Carbon::parse($message->created_at);
                }

                continue;
            }

            if ($senderId === $landlordId && isset($lastTenantMessage[$message->conversation_id])) {
                $start = $lastTenantMessage[$message->conversation_id];
                $delta = $start->diffInMinutes($message->created_at);
                $responseTimes[] = $delta;
                unset($lastTenantMessage[$message->conversation_id]);
            }
        }

        if (empty($responseTimes)) {
            return null;
        }

        sort($responseTimes, SORT_NUMERIC);
        $count = count($responseTimes);
        $middle = (int) floor(($count - 1) / 2);

        if ($count % 2) {
            return (int) $responseTimes[$middle];
        }

        return (int) floor(($responseTimes[$middle] + $responseTimes[$middle + 1]) / 2);
    }
}
