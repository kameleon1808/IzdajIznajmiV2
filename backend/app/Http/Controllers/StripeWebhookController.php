<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\RentalTransaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SentryReporter;
use App\Services\StructuredLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly StructuredLogger $log,
        private readonly SentryReporter $sentry
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            return response('Stripe webhook secret not configured', 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            $this->log->warning('stripe_webhook_invalid_signature', [
                'flow' => 'transaction_payment_webhook',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object);
                    break;
                case 'charge.succeeded':
                    $this->handleChargeSucceeded($event->data->object);
                    break;
            }
        } catch (\Throwable $e) {
            $context = [
                'flow' => 'transaction_payment_webhook',
                'event_type' => $event->type ?? null,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ];
            $this->log->error('stripe_webhook_processing_failed', $context);
            $this->sentry->captureException($e, $context);

            return response('Webhook processing failed', 500);
        }

        return response('ok', 200);
    }

    private function handleCheckoutCompleted($session): void
    {
        if (! isset($session->id)) {
            return;
        }

        $payment = Payment::where('provider_checkout_session_id', $session->id)->first();
        if (! $payment) {
            return;
        }

        $payment->status = Payment::STATUS_SUCCEEDED;
        if (isset($session->payment_intent)) {
            $payment->provider_intent_id = $session->payment_intent;
        }
        $payment->save();

        $this->advanceTransactionOnPayment($payment);
    }

    private function handlePaymentFailed($intent): void
    {
        if (! isset($intent->id)) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $intent->id)->first();
        if (! $payment) {
            return;
        }

        $payment->status = Payment::STATUS_FAILED;
        $payment->save();
    }

    private function handleChargeSucceeded($charge): void
    {
        if (! isset($charge->payment_intent)) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $charge->payment_intent)->first();
        if (! $payment) {
            return;
        }

        if (! empty($charge->receipt_url)) {
            $payment->receipt_url = $charge->receipt_url;
            $payment->save();
        }
    }

    private function handleChargeRefunded($charge): void
    {
        if (! isset($charge->payment_intent)) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $charge->payment_intent)->first();
        if (! $payment) {
            return;
        }

        $payment->status = Payment::STATUS_REFUNDED;
        $payment->receipt_url = $charge->receipt_url ?? $payment->receipt_url;
        $payment->save();
    }

    private function advanceTransactionOnPayment(Payment $payment): void
    {
        $transaction = RentalTransaction::find($payment->transaction_id);
        if (! $transaction) {
            return;
        }

        if ($payment->type === Payment::TYPE_DEPOSIT
            && ! in_array($transaction->status, [
                RentalTransaction::STATUS_DEPOSIT_PAID,
                RentalTransaction::STATUS_MOVE_IN_CONFIRMED,
                RentalTransaction::STATUS_COMPLETED,
            ], true)
        ) {
            $transaction->update(['status' => RentalTransaction::STATUS_DEPOSIT_PAID]);
            $this->notifyDepositPaid($transaction);
        }
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
                'url' => $this->transactionDeepLink($transaction->id),
            ]);
        }
    }

    private function transactionDeepLink(int $transactionId): string
    {
        return sprintf('/transactions/%d', $transactionId);
    }
}
