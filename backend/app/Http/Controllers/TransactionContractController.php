<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Notification;
use App\Models\RentalTransaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Transactions\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
        private readonly NotificationService $notifications
    ) {}

    public function store(GenerateContractRequest $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Only landlords can generate contracts');

        $isAdmin = $this->userHasRole($user, 'admin');
        abort_if(! $isAdmin && (int) $transaction->landlord_id !== (int) $user->id, 403, 'Forbidden');

        if (in_array($transaction->status, [
            RentalTransaction::STATUS_DEPOSIT_PAID,
            RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
            RentalTransaction::STATUS_COMPLETED,
            RentalTransaction::STATUS_CANCELLED,
            RentalTransaction::STATUS_DISPUTED,
        ], true)) {
            return response()->json(['message' => 'Cannot regenerate contract at this stage'], 422);
        }

        $contract = $this->contracts->generate($transaction, $request->validated());
        $contract->load('signatures');

        $transaction->update(['status' => RentalTransaction::STATUS_CONTRACT_GENERATED]);

        $transaction->loadMissing(['listing', 'seeker']);
        $this->notifyContractReady($transaction);

        $statusCode = $contract->wasRecentlyCreated ? 201 : 200;

        return response()->json(new ContractResource($contract), $statusCode);
    }

    public function latest(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        Gate::authorize('view', $transaction);

        $contract = $transaction->latestContract()->with('signatures')->first();
        if (! $contract) {
            return response()->json(['message' => 'No contract found'], 404);
        }

        return response()->json(new ContractResource($contract));
    }

    private function notifyContractReady(RentalTransaction $transaction): void
    {
        $listingTitle = $transaction->listing?->title ?? 'listing';
        $seeker = $transaction->seeker ?? User::find($transaction->seeker_id);
        $landlord = $transaction->landlord ?? User::find($transaction->landlord_id);

        if ($seeker) {
            $this->notifications->createNotification($seeker, Notification::TYPE_TRANSACTION_CONTRACT_READY, [
                'title' => 'Contract ready',
                'body' => sprintf('Your contract for "%s" is ready to review.', $listingTitle),
                'data' => [
                    'transaction_id' => $transaction->id,
                    'listing_id' => $transaction->listing_id,
                ],
                'url' => $this->transactionDeepLink($transaction->id),
            ]);
        }

        if ($landlord) {
            $this->notifications->createNotification($landlord, Notification::TYPE_TRANSACTION_CONTRACT_READY, [
                'title' => 'Contract prepared',
                'body' => sprintf('Contract for "%s" is ready to sign.', $listingTitle),
                'data' => [
                    'transaction_id' => $transaction->id,
                    'listing_id' => $transaction->listing_id,
                ],
                'url' => $this->transactionDeepLink($transaction->id),
            ]);
        }
    }

    private function transactionDeepLink(int $transactionId): string
    {
        return sprintf('/transactions/%d', $transactionId);
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
