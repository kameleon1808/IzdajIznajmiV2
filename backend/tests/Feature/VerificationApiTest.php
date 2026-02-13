<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_code(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified' => false,
        ]);

        $this->bootstrapCsrf();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/verification/email/request');

        $response->assertOk()->assertJsonPath('message', 'Verification code sent');
        $code = $response->json('devCode');
        $this->assertNotEmpty($code);

        $this->postJson('/api/v1/me/verification/email/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.emailVerified', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email_verified' => true,
        ]);
    }

    public function test_phone_verification_requires_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => null,
            'phone_verified' => false,
        ]);

        $this->bootstrapCsrf();

        $this->actingAs($user)
            ->postJson('/api/v1/me/verification/phone/request')
            ->assertStatus(422);
    }

    public function test_user_can_verify_phone_with_code(): void
    {
        $user = User::factory()->create([
            'phone' => '+38591111222',
            'phone_verified' => false,
        ]);

        $this->bootstrapCsrf();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/verification/phone/request');

        $response->assertOk();
        $code = $response->json('devCode');
        $this->assertNotEmpty($code);

        $this->postJson('/api/v1/me/verification/phone/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.phoneVerified', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone_verified' => true,
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
}
