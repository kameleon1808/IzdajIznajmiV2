<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner): Listing
    {
        $addressGuard = app(ListingAddressGuardService::class);
        $address = 'Viewing Street '.uniqid();
        $city = 'Split';
        $country = 'Croatia';
        $addressKey = $addressGuard->normalizeAddressKey($address, $city, $country);

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Viewing Ready Home',
            'address' => $address,
            'address_key' => $addressKey,
            'city' => $city,
            'country' => $country,
            'price_per_night' => 120,
            'rating' => 4.8,
            'reviews_count' => 10,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'apartment',
            'instant_book' => false,
            'status' => ListingStatusService::STATUS_ACTIVE,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_landlord_slot_crud_limited_to_owner(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $this->actingAs($landlord);
        $starts = Carbon::now()->addDays(2);
        $ends = $starts->clone()->addHour();

        $create = $this->postJson("/api/v1/listings/{$listing->id}/viewing-slots", [
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'capacity' => 2,
        ]);

        $create->assertCreated()->assertJsonPath('capacity', 2);
        $slotId = $create->json('id');

        $update = $this->patchJson("/api/v1/viewing-slots/{$slotId}", ['capacity' => 3]);
        $update->assertOk()->assertJsonPath('capacity', 3);

        $this->actingAs($otherLandlord);
        $this->patchJson("/api/v1/viewing-slots/{$slotId}", ['capacity' => 4])->assertForbidden();

        $seeker = User::factory()->create(['role' => 'seeker']);
        $this->actingAs($seeker);
        $this->postJson("/api/v1/viewing-slots/{$slotId}/request", ['message' => 'Looking forward'])->assertCreated();

        $this->actingAs($landlord);
        $this->deleteJson("/api/v1/viewing-slots/{$slotId}")->assertStatus(422);
    }

    public function test_seeker_cannot_double_book_slot(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $otherSeeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $starts = Carbon::now()->addDays(1);
        $ends = $starts->clone()->addHour();

        $this->actingAs($landlord);
        $slot = $this->postJson("/api/v1/listings/{$listing->id}/viewing-slots", [
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
        ])->json();

        $this->actingAs($seeker);
        $this->postJson("/api/v1/viewing-slots/{$slot['id']}/request", [])->assertCreated();

        $this->actingAs($otherSeeker);
        $response = $this->postJson("/api/v1/viewing-slots/{$slot['id']}/request", []);
        $response->assertStatus(422);
        $this->assertSame(1, ViewingRequest::count());
    }

    public function test_status_transitions_and_notifications(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $starts = Carbon::now()->addDays(3);
        $ends = $starts->clone()->addHour();

        $this->actingAs($landlord);
        $slot = $this->postJson("/api/v1/listings/{$listing->id}/viewing-slots", [
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
        ])->json();

        $this->actingAs($seeker);
        $createRequest = $this->postJson("/api/v1/viewing-slots/{$slot['id']}/request", ['message' => 'See you there']);
        $createRequest->assertCreated()->assertJsonPath('status', ViewingRequest::STATUS_REQUESTED);

        $viewingRequestId = $createRequest->json('id');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $landlord->id,
            'type' => Notification::TYPE_VIEWING_REQUESTED,
        ]);

        $this->actingAs($landlord);
        $confirm = $this->patchJson("/api/v1/viewing-requests/{$viewingRequestId}/confirm");
        $confirm->assertOk()->assertJsonPath('status', ViewingRequest::STATUS_CONFIRMED);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $seeker->id,
            'type' => Notification::TYPE_VIEWING_CONFIRMED,
        ]);

        $this->actingAs($seeker);
        $cancel = $this->patchJson("/api/v1/viewing-requests/{$viewingRequestId}/cancel");
        $cancel->assertOk()->assertJsonPath('status', ViewingRequest::STATUS_CANCELLED);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $landlord->id,
            'type' => Notification::TYPE_VIEWING_CANCELLED,
        ]);
    }

    public function test_ics_only_for_confirmed_participants(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $otherUser = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);

        $starts = Carbon::now()->addDays(4);
        $ends = $starts->clone()->addHour();

        $this->actingAs($landlord);
        $slot = $this->postJson("/api/v1/listings/{$listing->id}/viewing-slots", [
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
        ])->json();

        $this->actingAs($seeker);
        $create = $this->postJson("/api/v1/viewing-slots/{$slot['id']}/request", []);
        $requestId = $create->json('id');

        $this->actingAs($landlord);
        $this->patchJson("/api/v1/viewing-requests/{$requestId}/confirm")->assertOk();

        $this->actingAs($seeker);
        $ics = $this->get("/api/v1/viewing-requests/{$requestId}/ics");
        $ics->assertOk();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics->getContent());
        $this->assertStringContainsString('SUMMARY:Viewing', $ics->getContent());

        $this->actingAs($otherUser);
        $this->get("/api/v1/viewing-requests/{$requestId}/ics")->assertForbidden();

        $laterStarts = Carbon::now()->addDays(5);
        $laterEnds = $laterStarts->clone()->addHour();

        $this->actingAs($landlord);
        $secondSlot = $this->postJson("/api/v1/listings/{$listing->id}/viewing-slots", [
            'starts_at' => $laterStarts->toIso8601String(),
            'ends_at' => $laterEnds->toIso8601String(),
        ])->json();

        $this->actingAs($otherUser);
        $pendingCreate = $this->postJson("/api/v1/viewing-slots/{$secondSlot['id']}/request", []);
        $pendingId = $pendingCreate->json('id');

        $this->get("/api/v1/viewing-requests/{$pendingId}/ics")->assertNotFound();
    }
}
