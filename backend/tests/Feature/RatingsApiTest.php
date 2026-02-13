<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\Message;
use App\Models\Rating;
use App\Models\RatingReply;
use App\Models\RentalTransaction;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RatingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapCsrf();
    }

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

    private function createCompletedTransaction(Listing $listing, User $seeker, User $landlord): RentalTransaction
    {
        return RentalTransaction::create([
            'listing_id' => $listing->id,
            'landlord_id' => $landlord->id,
            'seeker_id' => $seeker->id,
            'status' => RentalTransaction::STATUS_COMPLETED,
            'deposit_amount' => 200,
            'rent_amount' => 800,
            'currency' => 'EUR',
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);
    }

    private function bootstrapCsrf(): void
    {
        $this->withCredentials();
        $response = $this->get('/sanctum/csrf-cookie');
        $response->assertNoContent();
        $this->stashCookiesFrom($response);
    }

    private function stashCookiesFrom(TestResponse $response): void
    {
        $cookies = collect($response->headers->getCookies())
            ->mapWithKeys(fn ($cookie) => [$cookie->getName() => $cookie->getValue()])
            ->all();

        if (empty($cookies)) {
            return;
        }

        $this->withUnencryptedCookies($cookies);

        if (isset($cookies['XSRF-TOKEN'])) {
            $this->withHeader('X-XSRF-TOKEN', $cookies['XSRF-TOKEN']);
        }
    }

    public function test_verified_user_can_rate_after_chat(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);
        $this->createCompletedTransaction($listing, $seeker, $landlord);

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
        $this->createCompletedTransaction($listing, $seeker, $landlord);

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
            $this->createCompletedTransaction($tempListing, $seeker, $tempLandlord);
            Rating::create([
                'listing_id' => $tempListing->id,
                'rater_id' => $seeker->id,
                'ratee_id' => $tempLandlord->id,
                'rating' => 4,
            ]);
        }

        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);
        $this->createCompletedTransaction($listing, $seeker, $landlord);

        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $landlord->id, 'rating' => 5])->assertStatus(429);
    }

    public function test_seeker_cannot_rate_before_transaction_completion(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);

        $this->actingAs($seeker);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $landlord->id, 'rating' => 4])
            ->assertStatus(403);
    }

    public function test_landlord_can_rate_seeker_after_completion(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);
        $this->createCompletedTransaction($listing, $seeker, $landlord);

        $this->actingAs($landlord);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $seeker->id, 'rating' => 5])
            ->assertCreated()
            ->assertJsonPath('rating', 5);
    }

    public function test_landlord_cannot_rate_before_completion(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);
        $this->createConversationWithMessages($listing, $seeker, $landlord);

        $this->actingAs($landlord);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['ratee_user_id' => $seeker->id, 'rating' => 4])
            ->assertStatus(403);
    }

    public function test_seeker_can_rate_listing_after_completion(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createCompletedTransaction($listing, $seeker, $landlord);

        $this->actingAs($seeker);
        $response = $this->postJson("/api/v1/listings/{$listing->id}/ratings", [
            'rating' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('rating', 5);
    }

    public function test_cannot_rate_listing_before_completion(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);

        $this->actingAs($seeker);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['rating' => 4])->assertStatus(403);
    }

    public function test_cannot_rate_listing_twice(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createCompletedTransaction($listing, $seeker, $landlord);

        $this->actingAs($seeker);
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['rating' => 4])->assertCreated();
        $this->postJson("/api/v1/listings/{$listing->id}/ratings", ['rating' => 3])->assertStatus(409);
    }

    public function test_report_listing_rating_once(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker', 'email_verified' => true, 'phone_verified' => true, 'address_verified' => true]);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $this->createCompletedTransaction($listing, $seeker, $landlord);

        $listingRating = ListingRating::create([
            'listing_id' => $listing->id,
            'seeker_id' => $seeker->id,
            'transaction_id' => RentalTransaction::where('listing_id', $listing->id)->first()?->id,
            'rating' => 4,
        ]);

        $this->actingAs($landlord);
        $this->postJson("/api/v1/listing-ratings/{$listingRating->id}/report", ['reason' => 'spam'])->assertCreated();
        $this->assertDatabaseHas('reports', [
            'target_type' => ListingRating::class,
            'target_id' => $listingRating->id,
        ]);
        $this->postJson("/api/v1/listing-ratings/{$listingRating->id}/report", ['reason' => 'spam'])->assertStatus(422);
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
        $this->assertDatabaseHas('reports', [
            'target_type' => Rating::class,
            'target_id' => $rating->id,
        ]);
        $this->postJson("/api/v1/ratings/{$rating->id}/report", ['reason' => 'spam'])->assertStatus(422);
    }

    public function test_only_profile_owner_can_report_rating(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'landlord']);
        $listing = $this->createListing($landlord);
        $rating = Rating::create([
            'listing_id' => $listing->id,
            'rater_id' => $seeker->id,
            'ratee_id' => $landlord->id,
            'rating' => 4,
        ]);

        $this->actingAs($seeker)
            ->postJson("/api/v1/ratings/{$rating->id}/report", ['reason' => 'spam'])
            ->assertStatus(403);

        $this->actingAs($other)
            ->postJson("/api/v1/ratings/{$rating->id}/report", ['reason' => 'spam'])
            ->assertStatus(403);

        $this->actingAs($landlord)
            ->postJson("/api/v1/ratings/{$rating->id}/report", ['reason' => 'spam'])
            ->assertCreated();
    }

    public function test_profile_owner_can_reply_once_and_admin_can_reply_multiple(): void
    {
        $seeker = User::factory()->create(['role' => 'seeker']);
        $landlord = User::factory()->create(['role' => 'landlord']);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $listing = $this->createListing($landlord);
        $rating = Rating::create([
            'listing_id' => $listing->id,
            'rater_id' => $seeker->id,
            'ratee_id' => $landlord->id,
            'rating' => 5,
        ]);

        $this->actingAs($landlord)
            ->postJson("/api/v1/ratings/{$rating->id}/replies", ['body' => 'Thanks!'])
            ->assertCreated();

        $this->postJson("/api/v1/ratings/{$rating->id}/replies", ['body' => 'Second reply'])
            ->assertStatus(409);

        $this->actingAs($admin)
            ->postJson("/api/v1/ratings/{$rating->id}/replies", ['body' => 'Admin reply'])
            ->assertCreated();

        $this->postJson("/api/v1/ratings/{$rating->id}/replies", ['body' => 'Admin follow-up'])
            ->assertCreated();

        $this->assertSame(3, RatingReply::where('rating_id', $rating->id)->count());
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
