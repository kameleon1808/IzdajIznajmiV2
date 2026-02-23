<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Rating;
use App\Models\Report;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function createListing(User $owner): Listing
    {
        $guard = app(ListingAddressGuardService::class);
        $address = 'Moderation Street '.uniqid();

        return Listing::create([
            'owner_id' => $owner->id,
            'title' => 'Moderated Listing',
            'address' => $address,
            'address_key' => $guard->normalizeAddressKey($address, 'Zagreb', 'Croatia'),
            'city' => 'Zagreb',
            'country' => 'Croatia',
            'price_per_month' => 100,
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

    public function test_non_admin_blocked_from_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $this->actingAs($user);

        $this->getJson('/api/v1/admin/kpi/summary')->assertStatus(403);
    }

    public function test_admin_can_view_queue_and_resolve_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $listing = $this->createListing($landlord);
        $rating = Rating::create([
            'listing_id' => $listing->id,
            'rater_id' => $seeker->id,
            'ratee_id' => $landlord->id,
            'rating' => 3,
        ]);
        $report = Report::create([
            'reporter_id' => $landlord->id,
            'target_type' => Rating::class,
            'target_id' => $rating->id,
            'reason' => 'abuse',
            'details' => 'Bad content',
        ]);

        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/moderation/queue')
            ->assertOk()
            ->assertJsonFragment(['id' => $report->id]);

        $this->patchJson("/api/v1/admin/moderation/reports/{$report->id}", [
            'action' => 'resolve',
            'delete_target' => true,
            'resolution' => 'Removed',
        ])->assertOk()
            ->assertJsonPath('status', 'resolved');

        $this->assertDatabaseMissing('ratings', ['id' => $rating->id]);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'resolved']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report.resolved',
            'subject_type' => Rating::class,
            'subject_id' => $rating->id,
        ]);
    }

    public function test_impersonation_flow_switches_user_and_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $seeker = User::factory()->create(['role' => 'seeker']);

        $this->bootstrapCsrf();

        $login = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password']);
        $login->assertOk();
        $this->stashCookiesFrom($login);

        $start = $this->postJson("/api/v1/admin/impersonate/{$seeker->id}");
        $start->assertOk()->assertJsonPath('impersonating', true);
        $this->stashCookiesFrom($start);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $seeker->id)
            ->assertJsonPath('impersonating', true);

        $stop = $this->postJson('/api/v1/admin/impersonate/stop');
        $stop->assertOk()->assertJsonPath('impersonating', false);
        $this->stashCookiesFrom($stop);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->assertEquals(2, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'impersonation.start', 'subject_id' => $seeker->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'impersonation.stop', 'subject_id' => $seeker->id]);
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
}
