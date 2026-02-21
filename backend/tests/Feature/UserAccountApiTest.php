<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);

        $this->bootstrapCsrf();

        $this->actingAs($user);
        $response = $this->patchJson('/api/v1/me/profile', [
            'full_name' => 'New Name',
            'phone' => '+38591111222',
            'address_book' => ['primary' => 'Zagreb, Croatia'],
        ]);

        $response->assertOk()->assertJsonPath('user.fullName', 'New Name');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'New Name',
            'phone' => '+38591111222',
        ]);
    }

    public function test_profile_phone_must_be_unique(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);
        User::factory()->create(['phone' => '+111222333']);

        $this->bootstrapCsrf();

        $this->actingAs($user);
        $this->patchJson('/api/v1/me/profile', [
            'phone' => '+111222333',
        ])->assertStatus(422);
    }

    public function test_phone_change_updates_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '+38591111222',
        ]);

        $this->bootstrapCsrf();

        $this->actingAs($user);
        $this->patchJson('/api/v1/me/profile', [
            'phone' => '+38591111223',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+38591111223',
        ]);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass123')]);

        $this->bootstrapCsrf();

        $this->actingAs($user);
        $this->patchJson('/api/v1/me/password', [
            'current_password' => 'oldpass123',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass123')]);

        $this->bootstrapCsrf();

        $this->actingAs($user);
        $this->patchJson('/api/v1/me/password', [
            'current_password' => 'wrongpass',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])->assertStatus(422);
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
