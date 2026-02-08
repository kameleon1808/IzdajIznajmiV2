<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RentalTransaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class TransactionPaymentController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function createDepositSession(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        Gate::authorize('pay', $transaction);

        if (in_array($transaction->status, [
            RentalTransaction::STATUS_DEPOSIT_PAID,
            RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
            RentalTransaction::STATUS_COMPLETED,
            RentalTransaction::STATUS_CANCELLED,
            RentalTransaction::STATUS_DISPUTED,
        ], true)) {
            return response()->json(['message' => 'Deposit payment not allowed'], 422);
        }

        $contract = $transaction->latestContract()->first();
        if (! $contract || $contract->status !== Contract::STATUS_FINAL) {
            return response()->json(['message' => 'Contract must be fully signed before payment'], 422);
        }

        if (! $transaction->deposit_amount || (float) $transaction->deposit_amount <= 0) {
            return response()->json(['message' => 'Deposit amount is missing'], 422);
        }

        $pending = $transaction->payments()
            ->where('type', Payment::TYPE_DEPOSIT)
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->first();

        if ($pending) {
            return response()->json(['message' => 'Deposit payment already pending'], 409);
        }

        $secret = config('services.stripe.secret');
        if (! $secret) {
            return response()->json(['message' => 'Stripe is not configured'], 422);
        }

        Stripe::setApiKey($secret);

        $frontendUrl = rtrim(config('app.frontend_url', ''), '/');
        $successUrl = $frontendUrl . "/transactions/{$transaction->id}?payment=success&session_id={CHECKOUT_SESSION_ID}";
        $cancelUrl = $frontendUrl . "/transactions/{$transaction->id}?payment=cancelled";

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'provider' => Payment::PROVIDER_STRIPE,
            'type' => Payment::TYPE_DEPOSIT,
            'amount' => $transaction->deposit_amount,
            'currency' => $transaction->currency,
            'status' => Payment::STATUS_PENDING,
        ]);

        try {
        $transaction->loadMissing('seeker');
        $customerEmail = $transaction->seeker?->email ?? $user->email;

        $session = Session::create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => strtolower($transaction->currency),
                            'unit_amount' => (int) round(((float) $transaction->deposit_amount) * 100),
                            'product_data' => [
                                'name' => 'Security Deposit',
                                'description' => 'Deposit for rental agreement',
                            ],
                        ],
                    ],
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'customer_email' => $customerEmail,
                'metadata' => [
                    'transaction_id' => (string) $transaction->id,
                    'payment_id' => (string) $payment->id,
                    'type' => Payment::TYPE_DEPOSIT,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'transaction_id' => (string) $transaction->id,
                        'payment_id' => (string) $payment->id,
                        'type' => Payment::TYPE_DEPOSIT,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $payment->update(['status' => Payment::STATUS_FAILED]);

            return response()->json(['message' => 'Unable to start Stripe Checkout'], 500);
        }

        $payment->update([
            'provider_checkout_session_id' => $session->id,
            'provider_intent_id' => $session->payment_intent ?? null,
        ]);

        return response()->json([
            'checkoutUrl' => $session->url,
            'payment' => new PaymentResource($payment),
        ]);
    }

    public function markDepositPaidCash(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        Gate::authorize('pay', $transaction);

        if (in_array($transaction->status, [
            RentalTransaction::STATUS_DEPOSIT_PAID,
            RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
            RentalTransaction::STATUS_COMPLETED,
            RentalTransaction::STATUS_CANCELLED,
            RentalTransaction::STATUS_DISPUTED,
        ], true)) {
            return response()->json(['message' => 'Deposit payment not allowed'], 422);
        }

        $contract = $transaction->latestContract()->first();
        if (! $contract || $contract->status !== Contract::STATUS_FINAL) {
            return response()->json(['message' => 'Contract must be fully signed before payment'], 422);
        }

        if (! $transaction->deposit_amount || (float) $transaction->deposit_amount <= 0) {
            return response()->json(['message' => 'Deposit amount is missing'], 422);
        }

        $existing = $transaction->payments()
            ->where('type', Payment::TYPE_DEPOSIT)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_SUCCEEDED])
            ->latest()
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Deposit payment already started'], 409);
        }

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'provider' => Payment::PROVIDER_CASH,
            'type' => Payment::TYPE_DEPOSIT,
            'amount' => $transaction->deposit_amount,
            'currency' => $transaction->currency,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        if (! in_array($transaction->status, [
            RentalTransaction::STATUS_DEPOSIT_PAID,
            RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
            RentalTransaction::STATUS_COMPLETED,
        ], true)
        ) {
            $transaction->update(['status' => RentalTransaction::STATUS_DEPOSIT_PAID]);
            $this->notifyDepositPaid($transaction);
        }

        $transaction->load(['listing.images', 'latestContract.signatures', 'payments']);

        return response()->json([
            'transaction' => new \App\Http\Resources\RentalTransactionResource($transaction),
            'payment' => new PaymentResource($payment),
        ]);
    }

    public function confirmMoveIn(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, 'landlord'), 403, 'Only landlords can confirm move-in');

        if ((int) $transaction->landlord_id !== (int) $user->id) {
            abort(403, 'Forbidden');
        }

        if ($transaction->status !== RentalTransaction::STATUS_DEPOSIT_PAID) {
            return response()->json(['message' => 'Move-in can only be confirmed after deposit payment'], 422);
        }

        $transaction->update(['status' => RentalTransaction::STATUS_MOVE_IN_CONFIRMED]);

        $this->notifyMoveInConfirmed($transaction);

        $transaction->load(['listing.images', 'latestContract.signatures', 'payments']);

        return response()->json(new \App\Http\Resources\RentalTransactionResource($transaction));
    }

    public function completeByLandlord(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, 'landlord'), 403, 'Only landlords can complete transactions');

        if ((int) $transaction->landlord_id !== (int) $user->id) {
            abort(403, 'Forbidden');
        }

        if ($transaction->status !== RentalTransaction::STATUS_MOVE_IN_CONFIRMED) {
            return response()->json(['message' => 'Completion only allowed after move-in confirmation'], 422);
        }

        $transaction->update([
            'status' => RentalTransaction::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $transaction->load(['listing.images', 'latestContract.signatures', 'payments']);

        return response()->json(new \App\Http\Resources\RentalTransactionResource($transaction));
    }

    private function notifyMoveInConfirmed(RentalTransaction $transaction): void
    {
        $transaction->loadMissing('listing');
        $listingTitle = $transaction->listing?->title ?? 'listing';
        $seeker = User::find($transaction->seeker_id);
        if ($seeker) {
            $this->notifications->createNotification($seeker, Notification::TYPE_TRANSACTION_MOVE_IN_CONFIRMED, [
                'title' => 'Move-in confirmed',
                'body' => sprintf('Move-in for "%s" has been confirmed by the landlord.', $listingTitle),
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

    private function notifyDepositPaid(RentalTransaction $transaction): void
    {
        $transaction->loadMissing('listing');
        $listingTitle = $transaction->listing?->title ?? 'listing';

        foreach ([$transaction->seeker_id, $transaction->landlord_id] as $userId) {
            $recipient = User::find($userId);
            if (! $recipient) {
                continue;
            }
            $this->notifications->createNotification($recipient, Notification::TYPE_TRANSACTION_DEPOSIT_PAID, [
                'title' => 'Deposit paid',
                'body' => sprintf('Deposit for "%s" has been paid.', $listingTitle),
                'data' => [
                    'transaction_id' => $transaction->id,
                    'listing_id' => $transaction->listing_id,
                ],
                'url' => sprintf('/transactions/%d', $transaction->id),
            ]);
        }
    }
}
