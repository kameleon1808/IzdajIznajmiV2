<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

        Mail::fake();
        $this->bootstrapCsrf();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/verification/email/request');

        $response->assertOk()->assertJsonPath('message', 'Verification code sent');
        $code = $response->json('devCode');
        $this->assertNotEmpty($code);
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use ($user, $code) {
            return $mail->hasTo($user->email) && $mail->code === $code;
        });

        $this->postJson('/api/v1/me/verification/email/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.emailVerified', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email_verified' => true,
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
