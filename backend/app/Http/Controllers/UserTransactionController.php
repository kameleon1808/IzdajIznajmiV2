<?php

namespace App\Http\Controllers;

use App\Http\Resources\RentalTransactionResource;
use App\Models\RentalTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserTransactionController extends Controller
{
    public function shared(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser, 401, 'Unauthenticated');

        $transactions = RentalTransaction::query()
            ->where(function ($query) use ($authUser, $user) {
                $query->where('landlord_id', $authUser->id)
                    ->where('seeker_id', $user->id);
            })
            ->orWhere(function ($query) use ($authUser, $user) {
                $query->where('landlord_id', $user->id)
                    ->where('seeker_id', $authUser->id);
            })
            ->with(['listing.images', 'latestContract.signatures', 'payments'])
            ->latest()
            ->get();

        return response()->json(RentalTransactionResource::collection($transactions));
    }
}
