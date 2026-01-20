<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingReportController extends Controller
{
    public function store(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = Report::where([
            'reporter_id' => $user->id,
            'target_type' => Listing::class,
            'target_id' => $listing->id,
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'You already reported this listing'], 422);
        }

        $report = Report::create([
            'reporter_id' => $user->id,
            'target_type' => Listing::class,
            'target_id' => $listing->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return response()->json($report, 201);
    }
}
