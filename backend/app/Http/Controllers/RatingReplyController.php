<?php

namespace App\Http\Controllers;

use App\Http\Resources\RatingResource;
use App\Models\Rating;
use App\Models\RatingReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingReplyController extends Controller
{
    public function store(Request $request, Rating $rating): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $isAdmin = $this->userHasRole($user, 'admin');
        if (! $isAdmin && (int) $rating->ratee_id !== (int) $user->id) {
            abort(403, 'Only the profile owner can reply to this rating');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        if (! $isAdmin) {
            $exists = RatingReply::where('rating_id', $rating->id)
                ->where('author_id', $user->id)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'You already replied to this rating'], 409);
            }
        }

        RatingReply::create([
            'rating_id' => $rating->id,
            'author_id' => $user->id,
            'body' => $data['body'],
            'is_admin' => $isAdmin,
        ]);

        return response()->json(
            new RatingResource($rating->load(['rater:id,name,full_name', 'listing:id,title,city', 'replies.author:id,name,full_name'])),
            201
        );
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
