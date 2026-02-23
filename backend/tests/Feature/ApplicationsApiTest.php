<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Listing;
use App\Models\RentalTransaction;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner, string $status = ListingStatusService::STATUS_ACTIVE): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $address = 'Test Street '.uniqid();
        $addressKey = $addressGuard->normalizeAddressKey($address, 'Split', 'Croatia');

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Seaside Villa',
            'address' => $address,
            'address_key' => $addressKey,
            'city' => 'Split',
            'country' => 'Croatia',
            'price_per_month' => 250,
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

    public function test_seeker_can_apply_once_to_active_listing(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $this->actingAs($seeker);

        $response = $this->postJson("/api/v1/listings/{$listing->id}/apply", [
            'message' => 'We would like to stay.',
            'startDate' => now()->addDays(7)->toDateString(),
            'endDate' => now()->addDays(7)->addMonthNoOverflow()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', Application::STATUS_SUBMITTED)
            ->assertJsonPath('startDate', now()->addDays(7)->toDateString())
            ->assertJsonPath('endDate', now()->addDays(7)->addMonthNoOverflow()->toDateString())
            ->assertJsonPath('currency', 'EUR');

        $this->assertDatabaseHas('applications', [
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
        ]);

        $stored = Application::query()
            ->where('listing_id', $listing->id)
            ->where('seeker_id', $seeker->id)
            ->firstOrFail();
        $this->assertSame(now()->addDays(7)->toDateString(), optional($stored->start_date)->toDateString());
        $this->assertSame(now()->addDays(7)->addMonthNoOverflow()->toDateString(), optional($stored->end_date)->toDateString());
    }

    public function test_applying_twice_is_blocked(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $this->actingAs($seeker);
        $this->postJson("/api/v1/listings/{$listing->id}/apply", [
            'message' => 'Hello',
            'startDate' => now()->addDays(7)->toDateString(),
            'endDate' => now()->addDays(7)->addMonthNoOverflow()->toDateString(),
        ]);

        $response = $this->postJson("/api/v1/listings/{$listing->id}/apply", [
            'message' => 'Another try',
            'startDate' => now()->addDays(9)->toDateString(),
            'endDate' => now()->addDays(9)->addMonthNoOverflow()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertCount(1, Application::all());
    }

    public function test_seeker_list_shows_only_their_applications(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $otherSeeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $otherListing = $this->createListing($landlord);

        Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'First',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        Application::create([
            'listing_id' => $otherListing->id,
            'seeker_id' => $otherSeeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'Second',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        $this->actingAs($seeker);

        $response = $this->getJson('/api/v1/seeker/applications');

        $response->assertOk();
        $payload = $response->json('data') ?? $response->json();
        $this->assertCount(1, $payload);
        $this->assertSame($listing->id, (int) $payload[0]['listing']['id']);
    }

    public function test_landlord_list_shows_only_their_listing_applications(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);
        $otherListing = $this->createListing($otherLandlord);

        Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'Hello',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        Application::create([
            'listing_id' => $otherListing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $otherLandlord->id,
            'message' => 'Not yours',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        $this->actingAs($landlord);
        $response = $this->getJson('/api/v1/landlord/applications');

        $response->assertOk();
        $payload = $response->json('data') ?? $response->json();
        $this->assertCount(1, $payload);
        $this->assertSame($listing->id, (int) $payload[0]['listing']['id']);
    }

    public function test_application_payload_flags_completed_transaction(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'Hello',
            'status' => Application::STATUS_ACCEPTED,
        ]);

        RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_COMPLETED,
            'deposit_amount' => 0,
            'rent_amount' => 250,
            'currency' => 'EUR',
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($landlord);
        $response = $this->getJson('/api/v1/landlord/applications');

        $response->assertOk();
        $payload = $response->json('data') ?? $response->json();
        $this->assertTrue($payload[0]['hasCompletedTransaction']);
    }

    public function test_cannot_apply_to_inactive_listing(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord, ListingStatusService::STATUS_PAUSED);

        $this->actingAs($seeker);

        $response = $this->postJson("/api/v1/listings/{$listing->id}/apply", [
            'message' => 'Trying to apply',
            'startDate' => now()->addDays(7)->toDateString(),
            'endDate' => now()->addDays(7)->addMonthNoOverflow()->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('applications', ['listing_id' => $listing->id]);
    }

    public function test_landlord_can_update_status_and_seeker_can_withdraw(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $application = Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'Hello',
            'status' => Application::STATUS_SUBMITTED,
        ]);

        $this->actingAs($landlord);
        $accept = $this->patchJson("/api/v1/applications/{$application->id}", ['status' => Application::STATUS_ACCEPTED]);
        $accept->assertOk()->assertJsonPath('status', Application::STATUS_ACCEPTED);

        $this->actingAs($seeker);
        $withdraw = $this->patchJson("/api/v1/applications/{$application->id}", ['status' => Application::STATUS_WITHDRAWN]);
        $withdraw->assertForbidden();
    }

    public function test_seeker_can_withdraw_submitted_application_and_withdrawn_at_is_recorded(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $application = Application::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'message' => 'Hello',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(7)->addMonthNoOverflow()->toDateString(),
            'status' => Application::STATUS_SUBMITTED,
        ]);

        $this->actingAs($seeker);
        $response = $this->patchJson("/api/v1/applications/{$application->id}", ['status' => Application::STATUS_WITHDRAWN]);

        $response->assertOk()
            ->assertJsonPath('status', Application::STATUS_WITHDRAWN);
        $this->assertIsString($response->json('withdrawnAt'));

        $this->assertNotNull($application->fresh()->withdrawn_at);
    }
}
