<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\RentalTransaction;
use App\Models\Signature;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SentryReporter;
use App\Services\StructuredLogger;
use App\Services\Transactions\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
        private readonly NotificationService $notifications,
        private readonly StructuredLogger $log,
        private readonly SentryReporter $sentry
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

        try {
            $contract = $this->contracts->generate($transaction, $request->validated());
        } catch (\Throwable $e) {
            $context = [
                'flow' => 'transaction_contract_generation',
                'transaction_id' => $transaction->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ];
            $this->log->error('transaction_contract_generation_failed', $context);
            $this->sentry->captureException($e, $context);

            throw $e;
        }
        $contract->load('signatures');

        $transaction->loadMissing(['listing', 'seeker', 'landlord']);
        $autoSigned = $this->autoSignContract($transaction, $contract);

        if ($autoSigned) {
            $transaction->update(['status' => RentalTransaction::STATUS_LANDLORD_SIGNED]);
            $this->notifyFullySigned($transaction);
        } else {
            $transaction->update(['status' => RentalTransaction::STATUS_CONTRACT_GENERATED]);
            $this->notifyContractReady($transaction);
        }

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

    private function autoSignContract(RentalTransaction $transaction, Contract $contract): bool
    {
        if ($contract->status === Contract::STATUS_FINAL) {
            return true;
        }

        $seeker = $transaction->seeker;
        $landlord = $transaction->landlord;
        if (! $seeker || ! $landlord) {
            return false;
        }

        $existing = $contract->signatures->keyBy('user_id');

        $this->createSignatureIfMissing($contract, $seeker, 'seeker', $existing);
        $this->createSignatureIfMissing($contract, $landlord, 'landlord', $existing);

        $contract->load('signatures');

        $hasSeeker = $contract->signatures->contains('role', 'seeker');
        $hasLandlord = $contract->signatures->contains('role', 'landlord');

        if ($hasSeeker && $hasLandlord) {
            $contract->update(['status' => Contract::STATUS_FINAL]);

            return true;
        }

        return false;
    }

    private function createSignatureIfMissing(Contract $contract, User $user, string $role, $existing): void
    {
        if ($existing->has($user->id)) {
            return;
        }

        Signature::create([
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'role' => $role,
            'signed_at' => now(),
            'ip' => null,
            'user_agent' => null,
            'signature_method' => Signature::METHOD_TYPED_NAME,
            'signature_data' => [
                'typed_name' => $user->full_name ?? $user->name ?? 'User',
                'consent' => true,
                'auto_signed' => true,
            ],
        ]);
    }

    private function notifyFullySigned(RentalTransaction $transaction): void
    {
        $listingTitle = $transaction->listing?->title ?? 'listing';
        foreach ([$transaction->seeker_id, $transaction->landlord_id] as $userId) {
            $recipient = User::find($userId);
            if (! $recipient) {
                continue;
            }
            $this->notifications->createNotification($recipient, Notification::TYPE_TRANSACTION_FULLY_SIGNED, [
                'title' => 'Contract fully signed',
                'body' => sprintf('Both parties signed the contract for "%s".', $listingTitle),
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
