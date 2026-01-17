<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthSanctumTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_cookie_endpoint_sets_tokens(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $response->assertCookie('XSRF-TOKEN');
        $response->assertCookie(config('session.cookie'));
    }

    public function test_register_then_me_with_session_cookie(): void
    {
        $this->bootstrapCsrf();

        $email = 'newuser@example.com';
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'full_name' => 'New User',
            'email' => $email,
            'phone' => '+385991200001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seeker',
        ]);

        $register->assertCreated()->assertJsonPath('user.email', $email);
        $this->stashCookiesFrom($register);

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.role', 'seeker');
    }

    public function test_login_and_me_return_roles(): void
    {
        $user = User::factory()->create([
            'email' => 'login-demo@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'landlord',
        ]);
        $user->syncRoles(['landlord']);

        $this->bootstrapCsrf();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $login->assertOk()->assertJsonPath('user.roles.0', 'landlord');
        $this->stashCookiesFrom($login);

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('user.roles.0', 'landlord');
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create(['email' => 'logout@example.com', 'password' => bcrypt('secret123')]);
        $this->bootstrapCsrf();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $this->stashCookiesFrom($login);

        $logout = $this->postJson('/api/v1/auth/logout');
        $logout->assertNoContent();
        $this->stashCookiesFrom($logout);

        app('auth')->forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_legacy_auth_routes_still_work(): void
    {
        $user = User::factory()->create(['email' => 'legacy@example.com', 'password' => bcrypt('secret123')]);

        $this->bootstrapCsrf();

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $this->stashCookiesFrom($login);
        $login->assertOk()->assertJsonPath('user.email', 'legacy@example.com');

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email', 'legacy@example.com');
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
