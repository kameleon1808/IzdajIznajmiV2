<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingRatingResource;
use App\Models\ListingRating;
use App\Models\RatingReport;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingRatingReportController extends Controller
{
    public function store(Request $request, ListingRating $listingRating): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        if (! $this->isAllowedReporter($user)) {
            abort(403, 'Only seekers or landlords can report');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = RatingReport::where('listing_rating_id', $listingRating->id)
            ->where('reporter_id', $user->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'You already reported this rating'], 422);
        }

        RatingReport::create([
            'listing_rating_id' => $listingRating->id,
            'reporter_id' => $user->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        Report::firstOrCreate(
            [
                'reporter_id' => $user->id,
                'target_type' => ListingRating::class,
                'target_id' => $listingRating->id,
            ],
            [
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'status' => 'open',
            ]
        );

        app(\App\Services\StructuredLogger::class)->info('listing_rating_reported', [
            'listing_rating_id' => $listingRating->id,
            'listing_id' => $listingRating->listing_id,
            'user_id' => $user->id,
        ]);

        return response()->json(new ListingRatingResource($listingRating->loadCount('reports')), 201);
    }

    private function isAllowedReporter($user): bool
    {
        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['seeker', 'landlord']))
            || ($user && in_array($user->role, ['seeker', 'landlord'], true));
    }
}
