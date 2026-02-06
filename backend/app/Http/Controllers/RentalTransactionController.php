<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRentalTransactionRequest;
use App\Http\Resources\RentalTransactionResource;
use App\Models\Listing;
use App\Models\RentalTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RentalTransactionController extends Controller
{
    public function store(StoreRentalTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Only landlords can start transactions');

        $payload = $request->validated();
        $listing = Listing::findOrFail($payload['listing_id']);
        $isAdmin = $this->userHasRole($user, 'admin');

        abort_if(! $isAdmin && (int) $listing->owner_id !== (int) $user->id, 403, 'Forbidden');

        $seeker = User::findOrFail($payload['seeker_id']);
        abort_if((int) $seeker->id === (int) $listing->owner_id, 422, 'Seeker must be different from landlord');

        $currency = $payload['currency'] ?? config('transactions.default_currency', 'EUR');

        try {
            $transaction = DB::transaction(function () use ($listing, $seeker, $payload, $currency) {
                $active = RentalTransaction::where('listing_id', $listing->id)
                    ->where('seeker_id', $seeker->id)
                    ->whereIn('status', [
                        RentalTransaction::STATUS_INITIATED,
                        RentalTransaction::STATUS_CONTRACT_GENERATED,
                        RentalTransaction::STATUS_SEEKER_SIGNED,
                        RentalTransaction::STATUS_LANDLORD_SIGNED,
                        RentalTransaction::STATUS_DEPOSIT_PAID,
                        RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
                        RentalTransaction::STATUS_DISPUTED,
                    ])
                    ->lockForUpdate()
                    ->first();

                if ($active) {
                    throw new \RuntimeException('Active transaction already exists');
                }

                return RentalTransaction::create([
                    'listing_id' => $listing->id,
                    'landlord_id' => $listing->owner_id,
                    'seeker_id' => $seeker->id,
                    'status' => RentalTransaction::STATUS_INITIATED,
                    'deposit_amount' => $payload['deposit_amount'] ?? null,
                    'rent_amount' => $payload['rent_amount'] ?? null,
                    'currency' => $currency,
                    'started_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Active transaction already exists'], 409);
        }

        $transaction->load(['listing.images']);

        return response()->json(new RentalTransactionResource($transaction), 201);
    }

    public function show(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        Gate::authorize('view', $transaction);

        $transaction->load(['listing.images', 'latestContract.signatures', 'payments']);

        return response()->json(new RentalTransactionResource($transaction));
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
