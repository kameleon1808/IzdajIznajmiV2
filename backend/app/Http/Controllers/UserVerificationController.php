<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserVerificationCode;
use App\Services\Verification\EmailVerificationCodeSender;
use App\Services\Verification\Exceptions\VerificationDeliveryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationCodeSender $emailSender) {}

    public function requestEmail(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        if (! $user->email) {
            return response()->json(['message' => 'Email is missing'], 422);
        }

        if ($user->email_verified) {
            return response()->json(['message' => 'Email already verified'], 409);
        }

        return $this->issueCode($user, UserVerificationCode::CHANNEL_EMAIL);
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->requireEmail($user);

        return $this->confirmCode($request, $user, UserVerificationCode::CHANNEL_EMAIL);
    }

    private function issueCode(User $user, string $channel): JsonResponse
    {
        $code = $this->generateCode();
        $hash = $this->hashCode($code);
        $expiresAt = now()->addMinutes((int) config('verification.code_ttl_minutes', 10));

        UserVerificationCode::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->delete();

        $verificationCode = UserVerificationCode::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'code_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        try {
            $this->dispatchCode($user, $channel, $code);
        } catch (VerificationDeliveryException $e) {
            $verificationCode->delete();

            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        $destination = $this->maskEmail($user->email ?? '');

        $response = [
            'message' => 'Verification code sent',
            'destination' => $destination,
        ];

        if (app()->environment(['local', 'testing'])) {
            $response['devCode'] = $code;
        }

        return response()->json($response);
    }

    private function confirmCode(Request $request, User $user, string $channel): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:10', 'regex:/^[0-9]+$/'],
        ]);

        $record = UserVerificationCode::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Verification code not found'], 404);
        }

        if ($record->expires_at->isPast()) {
            return response()->json(['message' => 'Verification code expired'], 422);
        }

        if (! hash_equals($record->code_hash, $this->hashCode($data['code']))) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        $record->update(['consumed_at' => now()]);

        $user->forceFill([
            'email_verified' => true,
            'email_verified_at' => now(),
        ])->save();

        return response()->json(['user' => new UserResource($user->fresh('roles'))]);
    }

    private function requireUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        return $user;
    }

    private function requireEmail(User $user): void
    {
        if (! $user->email) {
            abort(422, 'Email is missing');
        }
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    private function dispatchCode(User $user, string $channel, string $code): void
    {
        if ($channel !== UserVerificationCode::CHANNEL_EMAIL) {
            throw new VerificationDeliveryException('Unsupported verification channel.');
        }

        $this->emailSender->send($user, $code);
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);
        $maskedName = strlen($name) <= 2 ? str_repeat('*', strlen($name)) : substr($name, 0, 2).str_repeat('*', max(strlen($name) - 2, 0));

        return $maskedName.'@'.$domain;
    }
}
