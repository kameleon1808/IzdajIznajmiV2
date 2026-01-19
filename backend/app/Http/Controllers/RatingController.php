<?php

namespace App\Http\Controllers;

use App\Http\Resources\RatingResource;
use App\Models\Listing;
use App\Models\Rating;
use App\Models\User;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RatingController extends Controller
{
    public function __construct(private RatingService $ratingService)
    {
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'ratee_user_id' => ['required', 'exists:users,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rateeId = (int) $data['ratee_user_id'];
        $rating = (int) $data['rating'];
        $comment = $data['comment'] ?? null;

        $this->ratingService->assertCanRate($user, $listing, $rateeId);

        $record = $this->ratingService->createRating(
            $user,
            $listing,
            $rateeId,
            $rating,
            $comment,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json(new RatingResource($record->load('rater', 'listing')), 201);
    }

    public function userRatings(User $user): JsonResponse
    {
        $ratings = Rating::with(['rater:id,name,full_name', 'listing:id,title,city'])
            ->where('ratee_id', $user->id)
            ->latest()
            ->paginate(10);

        return RatingResource::collection($ratings)->response();
    }

    public function myRatings(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $ratings = Rating::with(['ratee:id,name,full_name', 'listing:id,title,city'])
            ->where('rater_id', $user->id)
            ->latest()
            ->paginate(10);

        return RatingResource::collection($ratings)->response();
    }
}
