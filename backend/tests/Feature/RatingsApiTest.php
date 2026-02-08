<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Rating;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner): Listing
    {
        $guard = app(ListingAddressGuardService::class);
        $address = 'Rating Street '.uniqid();

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Rating Listing',
            'address' => $address,
            'address_key' => $guard->normalizeAddressKey($address, 'Zagreb', 'Croatia'),
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_night' => 100,
            'rating' => 4.5,
            'reviews_count' => 0,
            'beds' => 2,
            'baths' => 1,
            'rooms' => 2,
            'category' => 'apartment',
            'instant_book' => true,
            'status' => 'active',
            'published_at' => now(),
        ]);
    }

    private function createConversationWithMessages(Listing $listing, User $seeker, User $landlord): void
    {
        $conversation = Conversation::create([
            'listing_id' => $listing->id,
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $seeker->id,
            'body' => 'Hello',
        ]);
    }

    public function test_verified_user_can_rate_after_chat(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);

        $this->actingAs($seeker);
        $response = $this->postJson("/api/v1/listings/{$listing->id}/ratings", [
            'ratee_user_id' => $landlord->id,
            'rating' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('rating', 5);
    }

    public function test_cannot_rate_twice_same_pair(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);

        $this->actingAs($seeker);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $landlord->id, 'rating' => 4]);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $landlord->id, 'rating' => 3])->assertStatus(409);
    }

    public function test_rate_limit_five_per_day(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($seeker);
        for ($i = 0; $i < 5; $i++) {
            $tempLandlord = $i === 0 ? $landlord : User::factory()->create(['role' => 'landlord']);
            $tempListing = $this->createListing($tempLandlord);
            $this->createConversationWithMessages($tempListing, $seeker, $tempLandlord);
            Rating::create([
                'listing_id' => $tempListing->id,
                'rater_id' => $seeker->id,
                'ratee_id' => $tempLandlord->id,
                'rating' => 4,
            ]);
        }

        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);

        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $landlord->id, 'rating' => 5])->assertStatus(429);
    }

    public function test_report_rating_once(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);
        $rating = Rating::create([
            'listing_id' => $listing->id,
            'rater_id' => $seeker->id,
            'ratee_id' => $landlord->id,
            'rating' => 4,
        ]);

        $this->actingAs($landlord);
        $this->postJson("/api/v1/ratings/{$rating->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->postJson("/api/v1/ratings/{$rating->id}/report", ['reason' => 'spam'])->assertStatus(422);
    }

    public function test_admin_can_delete_rating_and_flag_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $rating = Rating::create([
            'listing_id' => $listing->id,
            'rater_id' => $seeker->id,
            'ratee_id' => $landlord->id,
            'rating' => 4,
        ]);

        $this->actingAs($admin);
        $this->deleteJson("/api/v1/admin/ratings/{$rating->id}")->assertOk();
        $this->patchJson("/api/v1/admin/users/{$landlord->id}/flag-suspicious", ['is_suspicious' => true])
            ->assertOk()
            ->assertJsonPath('isSuspicious', true);
    }
}
