<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_sessions(): void
    {
        $user = User::factory()->create(['email' => 'session@example.com', 'password' => bcrypt('secret123')]);

        $this->bootstrapCsrf();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
        $this->stashCookiesFrom($login);

        $sessions = $this->getJson('/api/v1/security/sessions');
        $sessions->assertOk()->assertJsonStructure(['currentSessionId', 'sessions']);
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
