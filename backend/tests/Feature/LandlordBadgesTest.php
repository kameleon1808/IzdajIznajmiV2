<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Rating;
use App\Models\RentalTransaction;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\LandlordMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandlordBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_awarded_when_metrics_meet_thresholds(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $listing = Listing::factory()->create(['owner_id' => $landlord->id]);

        $raters = User::factory()->count(5)->create(['role' => 'seeker']);
        foreach ($raters as $rater) {
            Rating::create([
                'listing_id' => $listing->id,
                'rater_id' => $rater->id,
                'ratee_id' => $landlord->id,
                'rating' => 5,
                'comment' => 'Great',
            ]);
        }

        $seeker = User::factory()->create(['role' => 'seeker']);
        $transactionListing = Listing::factory()->create(['owner_id' => $landlord->id]);
        for ($i = 0; $i < 3; $i++) {
            RentalTransaction::create([
                'listing_id' => $transactionListing->id,
                'landlord_id' => $landlord->id,
                'seeker_id' => $seeker->id,
                'status' => RentalTransaction::STATUS_COMPLETED,
            ]);
        }

        $conversation = Conversation::create([
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listing->id,
        ]);

        $now = now();
        DB::table('messages')->insert([
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $seeker->id,
                'body' => 'Hello',
                'created_at' => $now->copy()->subHours(5),
                'updated_at' => $now->copy()->subHours(5),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $landlord->id,
                'body' => 'Hi there',
                'created_at' => $now->copy()->subHours(4),
                'updated_at' => $now->copy()->subHours(4),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $seeker->id,
                'body' => 'Thanks',
                'created_at' => $now->copy()->subHours(3),
                'updated_at' => $now->copy()->subHours(3),
            ],
            [
                'conversation_id' => $conversation->id,
                'sender_id' => $landlord->id,
                'body' => 'Anytime',
                'created_at' => $now->copy()->subHours(2),
                'updated_at' => $now->copy()->subHours(2),
            ],
        ]);

        app(LandlordMetricsService::class)->recomputeForLandlord($landlord);
        $landlord->load('landlordMetric');

        $badges = app(BadgeService::class)->badgesFor($landlord, $landlord->landlordMetric);

        $this->assertContains(BadgeService::BADGE_TOP_LANDLORD, $badges);
    }

    public function test_badge_override_can_hide_badge(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $landlord->badge_override_json = [BadgeService::BADGE_TOP_LANDLORD => false];
        $landlord->save();

        $badges = app(BadgeService::class)->badgesFor($landlord);

        $this->assertEmpty($badges);
    }
}
