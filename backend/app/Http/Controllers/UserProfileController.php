<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json(new PublicUserResource($user));
    }
}
