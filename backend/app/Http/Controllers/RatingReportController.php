<?php

namespace App\Http\Controllers;

use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Models\RatingReport;
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

        return response()->json(new RatingResource($rating->loadCount('reports')), 201);
    }
}
