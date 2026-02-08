<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\RentalTransaction;
use App\Models\Signature;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class ContractSignatureController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function sign(SignContractRequest $request, Contract $contract): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $contract->loadMissing(['transaction', 'signatures']);
        $transaction = $contract->transaction;
        abort_if(! $transaction, 404, 'Transaction not found');
        $transaction->loadMissing('listing');

        if (in_array($transaction->status, [
            RentalTransaction::STATUS_DEPOSIT_PAID,
            RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
            RentalTransaction::STATUS_COMPLETED,
            RentalTransaction::STATUS_CANCELLED,
            RentalTransaction::STATUS_DISPUTED,
        ], true)) {
            return response()->json(['message' => 'Contract signing is closed for this transaction'], 422);
        }

        if (! $this->isParticipant($user, $transaction)) {
            abort(403, 'Forbidden');
        }

        $latestId = $transaction->latestContract()->value('id');
        if ((int) $latestId !== (int) $contract->id) {
            return response()->json(['message' => 'Only the latest contract can be signed'], 422);
        }

        if ($contract->status === Contract::STATUS_FINAL) {
            return response()->json(['message' => 'Contract already fully signed'], 422);
        }

        $role = $this->resolveRole($user, $transaction);
        if (! $role) {
            abort(403, 'Forbidden');
        }

        if ($contract->signatures->firstWhere('user_id', $user->id)) {
            return response()->json(['message' => 'You already signed this contract'], 409);
        }

        Signature::create([
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'role' => $role,
            'signed_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'signature_method' => Signature::METHOD_TYPED_NAME,
            'signature_data' => [
                'typed_name' => $request->input('typed_name'),
                'consent' => true,
            ],
        ]);

        $contract->load('signatures');

        $hasSeeker = $contract->signatures->contains('role', 'seeker');
        $hasLandlord = $contract->signatures->contains('role', 'landlord');

        if ($hasSeeker && $hasLandlord) {
            $contract->update(['status' => Contract::STATUS_FINAL]);
            $transaction->update(['status' => RentalTransaction::STATUS_LANDLORD_SIGNED]);
            $this->notifyFullySigned($transaction, $user);
        } else {
            $transaction->update([
                'status' => $role === 'seeker'
                    ? RentalTransaction::STATUS_SEEKER_SIGNED
                    : RentalTransaction::STATUS_LANDLORD_SIGNED,
            ]);
            $this->notifyOtherPartySigned($transaction, $user);
        }

        return response()->json(new ContractResource($contract->load('signatures')));
    }

    private function isParticipant(User $user, RentalTransaction $transaction): bool
    {
        return $transaction->landlord_id === $user->id
            || $transaction->seeker_id === $user->id
            || $this->userHasRole($user, 'admin');
    }

    private function resolveRole(User $user, RentalTransaction $transaction): ?string
    {
        if ($transaction->seeker_id === $user->id) {
            return 'seeker';
        }

        if ($transaction->landlord_id === $user->id) {
            return 'landlord';
        }

        return null;
    }

    private function notifyOtherPartySigned(RentalTransaction $transaction, User $signer): void
    {
        $listingTitle = $transaction->listing?->title ?? 'listing';
        $recipientId = $transaction->seeker_id === $signer->id ? $transaction->landlord_id : $transaction->seeker_id;
        $recipient = User::find($recipientId);

        if (! $recipient) {
            return;
        }

        $this->notifications->createNotification($recipient, Notification::TYPE_TRANSACTION_SIGNED_BY_OTHER_PARTY, [
            'title' => 'Contract signed',
            'body' => sprintf('%s signed the contract for "%s".', $signer->name ?? 'The other party', $listingTitle),
            'data' => [
                'transaction_id' => $transaction->id,
                'listing_id' => $transaction->listing_id,
            ],
            'url' => $this->transactionDeepLink($transaction->id),
        ]);
    }

    private function notifyFullySigned(RentalTransaction $transaction, User $signer): void
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
