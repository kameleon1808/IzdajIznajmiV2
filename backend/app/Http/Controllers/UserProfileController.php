<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    public function show(User $user): JsonResponse
    {
        if (!$this->isLandlord($user)) {
            abort(404);
        }

        return response()->json(new PublicUserResource($user));
    }

    private function isLandlord(User $user): bool
    {
        return (method_exists($user, 'hasRole') && $user->hasRole('landlord')) || $user->role === 'landlord';
    }
}
