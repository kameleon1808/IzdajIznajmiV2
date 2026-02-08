<?php

namespace App\Services;

use App\Models\SearchFilterSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;

class SearchFilterSnapshotService
{
    public function record(User $user, array $filters, int $maxSnapshots = 10): void
    {
        if (! $this->isSeeker($user)) {
            return;
        }

        if (empty($filters)) {
            return;
        }

        SearchFilterSnapshot::create([
            'user_id' => $user->id,
            'filters' => $filters,
        ]);

        $staleIds = SearchFilterSnapshot::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id'])
            ->pluck('id')
            ->slice($maxSnapshots)
            ->values();

        if ($staleIds->isNotEmpty()) {
            SearchFilterSnapshot::whereIn('id', $staleIds)->delete();
        }
    }

    public function recent(User $user, int $limit = 5): Collection
    {
        return SearchFilterSnapshot::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    private function isSeeker(User $user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('seeker')) || $user->role === 'seeker';
    }
}
