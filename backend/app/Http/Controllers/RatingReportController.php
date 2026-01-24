<?php

namespace App\Http\Controllers;

use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Models\RatingReport;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingReportController extends Controller
{
    public function store(Request $request, Rating $rating): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        if (!in_array($user->id, [$rating->rater_id, $rating->ratee_id], true)) {
            abort(403, 'Only participants can report');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = RatingReport::where('rating_id', $rating->id)->where('reporter_id', $user->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'You already reported this rating'], 422);
        }

        RatingReport::create([
            'rating_id' => $rating->id,
            'reporter_id' => $user->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        Report::firstOrCreate(
            [
                'reporter_id' => $user->id,
                'target_type' => Rating::class,
                'target_id' => $rating->id,
            ],
            [
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'status' => 'open',
            ]
        );

        app(\App\Services\StructuredLogger::class)->info('rating_reported', [
            'rating_id' => $rating->id,
            'listing_id' => $rating->listing_id,
            'user_id' => $user->id,
        ]);

        return response()->json(new RatingResource($rating->loadCount('reports')), 201);
    }
}
