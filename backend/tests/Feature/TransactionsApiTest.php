<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\RentalTransaction;
use App\Models\Signature;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner, string $status = ListingStatusService::STATUS_ACTIVE): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $address = 'Test Street ' . uniqid();
        $addressKey = $addressGuard->normalizeAddressKey($address, 'Split', 'Croatia');

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Seaside Villa',
            'address' => $address,
            'address_key' => $addressKey,
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_night' => 250,
            'rating' => 4.9,
            'reviews_count' => 30,
            'beds' => 3,
            'baths' => 2,
            'rooms' => 3,
            'category' => 'villa',
            'instant_book' => false,
            'status' => $status,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_landlord_can_start_transaction_and_seeker_cannot(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $this->actingAs($landlord);
        $response = $this->postJson('/api/v1/transactions', [
            'listingId' => $listing->id,
            'seekerId' => $seeker->id,
            'depositAmount' => 500,
            'rentAmount' => 1200,
        ]);

        $response->assertCreated()->assertJsonPath('status', RentalTransaction::STATUS_INITIATED);

        $this->actingAs($seeker);
        $forbidden = $this->postJson('/api/v1/transactions', [
            'listingId' => $listing->id,
            'seekerId' => $seeker->id,
        ]);
        $forbidden->assertForbidden();
    }

    public function test_contract_generation_creates_pdf_on_private_disk(): void
    {
        Storage::fake('private');

        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $transaction = RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_INITIATED,
            'deposit_amount' => 400,
            'rent_amount' => 1200,
            'currency' => 'EUR',
            'started_at' => now(),
        ]);

        $this->actingAs($landlord);
        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/contracts", [
            'startDate' => now()->addWeek()->toDateString(),
        ]);

        $response->assertStatus(201);

        $contract = Contract::first();
        $this->assertNotNull($contract);
        Storage::disk('private')->assertExists($contract->pdf_path);
    }

    public function test_signing_records_ip_and_finalizes_contract(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $transaction = RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_CONTRACT_GENERATED,
            'deposit_amount' => 400,
            'rent_amount' => 1200,
            'currency' => 'EUR',
            'started_at' => now(),
        ]);

        $contract = Contract::create([
            'transaction_id' => $transaction->id,
            'version' => 1,
            'template_key' => 'standard_v1',
            'contract_hash' => 'hash',
            'pdf_path' => 'contracts/1/contract_v1.pdf',
            'rendered_payload' => ['start_date' => now()->toDateString()],
            'status' => Contract::STATUS_DRAFT,
        ]);

        $this->actingAs($seeker);
        $sign1 = $this->postJson("/api/v1/contracts/{$contract->id}/sign", [
            'typedName' => 'Seeker Name',
            'consent' => true,
        ]);

        $sign1->assertOk();
        $signature = Signature::where('contract_id', $contract->id)->where('user_id', $seeker->id)->first();
        $this->assertNotNull($signature);
        $this->assertNotEmpty($signature->ip);

        $this->actingAs($landlord);
        $sign2 = $this->postJson("/api/v1/contracts/{$contract->id}/sign", [
            'typedName' => 'Landlord Name',
            'consent' => true,
        ]);

        $sign2->assertOk();
        $contract->refresh();
        $transaction->refresh();

        $this->assertSame(Contract::STATUS_FINAL, $contract->status);
        $this->assertSame(RentalTransaction::STATUS_LANDLORD_SIGNED, $transaction->status);
    }

    public function test_stripe_webhook_updates_payment_status(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $transaction = RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_LANDLORD_SIGNED,
            'deposit_amount' => 400,
            'rent_amount' => 1200,
            'currency' => 'EUR',
            'started_at' => now(),
        ]);

        $payment = Payment::create([
            'transaction_id' => $transaction->id,
            'provider' => Payment::PROVIDER_STRIPE,
            'type' => Payment::TYPE_DEPOSIT,
            'amount' => 400,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'provider_checkout_session_id' => 'cs_test_123',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_intent' => 'pi_test_123',
                ],
            ],
        ]);

        config(['services.stripe.webhook_secret' => 'whsec_test']);
        config(['services.stripe.secret' => 'sk_test']);

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, 'whsec_test');
        $header = "t={$timestamp},v1={$signature}";

        $response = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $header,
        ], $payload);

        $response->assertOk();
        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame(RentalTransaction::STATUS_DEPOSIT_PAID, $transaction->status);
    }

    public function test_non_participant_cannot_view_transaction(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $other = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $transaction = RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_INITIATED,
            'started_at' => now(),
        ]);

        $this->actingAs($other);
        $response = $this->getJson("/api/v1/transactions/{$transaction->id}");
        $response->assertForbidden();
    }

    public function test_cannot_pay_before_contract_signed(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $transaction = RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_CONTRACT_GENERATED,
            'deposit_amount' => 400,
            'rent_amount' => 1200,
            'currency' => 'EUR',
            'started_at' => now(),
        ]);

        Contract::create([
            'transaction_id' => $transaction->id,
            'version' => 1,
            'template_key' => 'standard_v1',
            'contract_hash' => 'hash',
            'pdf_path' => 'contracts/1/contract_v1.pdf',
            'rendered_payload' => ['start_date' => now()->toDateString()],
            'status' => Contract::STATUS_DRAFT,
        ]);

        $this->actingAs($seeker);
        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/payments/deposit/session");

        $response->assertStatus(422);
    }
}
