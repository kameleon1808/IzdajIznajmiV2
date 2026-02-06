<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OTPHP\TOTP;
use Tests\TestCase;

class MfaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_and_confirm_enables_mfa(): void
    {
        $user = User::factory()->create(['email' => 'mfa@example.com', 'password' => bcrypt('secret123')]);

        $this->bootstrapCsrf();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $this->stashCookiesFrom($login);

        $setup = $this->postJson('/api/v1/security/mfa/setup');
        $setup->assertOk()->assertJsonStructure(['secret', 'otpauth_url', 'qr_svg', 'recovery_codes']);
        $secret = $setup->json('secret');
        $code = TOTP::create($secret)->now();

        $confirm = $this->postJson('/api/v1/security/mfa/confirm', ['code' => $code]);
        $confirm->assertOk();

        $this->assertTrue($user->fresh()->mfa_enabled);
    }

    public function test_login_requires_mfa_and_recovery_code_is_one_time(): void
    {
        $user = User::factory()->create(['email' => 'mfa2@example.com', 'password' => bcrypt('secret123')]);

        $this->bootstrapCsrf();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $this->stashCookiesFrom($login);

        $setup = $this->postJson('/api/v1/security/mfa/setup');
        $secret = $setup->json('secret');
        $recoveryCode = $setup->json('recovery_codes.0');
        $code = TOTP::create($secret)->now();
        $this->postJson('/api/v1/security/mfa/confirm', ['code' => $code])->assertOk();

        $this->postJson('/api/v1/auth/logout');
        $this->bootstrapCsrf();
        $login2 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $login2->assertStatus(202)->assertJsonPath('mfa_required', true);
        $this->stashCookiesFrom($login2);

        $challengeId = $login2->json('challenge_id');
        $verify = $this->postJson('/api/v1/security/mfa/verify', [
            'challenge_id' => $challengeId,
            'recovery_code' => $recoveryCode,
        ]);
        $verify->assertOk();

        $this->postJson('/api/v1/auth/logout');
        $this->bootstrapCsrf();
        $login3 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $this->stashCookiesFrom($login3);
        $challengeId2 = $login3->json('challenge_id');

        $reuse = $this->postJson('/api/v1/security/mfa/verify', [
            'challenge_id' => $challengeId2,
            'recovery_code' => $recoveryCode,
        ]);
        $reuse->assertStatus(422);
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
