<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserVerificationCode;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserVerificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

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

    public function requestPhone(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        if (! $user->phone) {
            return response()->json(['message' => 'Phone number is missing'], 422);
        }

        if ($user->phone_verified) {
            return response()->json(['message' => 'Phone already verified'], 409);
        }

        return $this->issueCode($user, UserVerificationCode::CHANNEL_PHONE);
    }

    public function confirmPhone(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if (! $user->phone) {
            return response()->json(['message' => 'Phone number is missing'], 422);
        }

        return $this->confirmCode($request, $user, UserVerificationCode::CHANNEL_PHONE);
    }

    private function issueCode(User $user, string $channel): JsonResponse
    {
        $code = $this->generateCode();
        $hash = $this->hashCode($code);
        $expiresAt = now()->addMinutes(10);

        UserVerificationCode::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->delete();

        UserVerificationCode::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'code_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        $destination = $channel === UserVerificationCode::CHANNEL_EMAIL
            ? $this->maskEmail($user->email ?? '')
            : $this->maskPhone($user->phone ?? '');

        $title = $channel === UserVerificationCode::CHANNEL_EMAIL
            ? 'Email verification code'
            : 'Phone verification code';
        $body = sprintf('Your verification code is %s. It expires in 10 minutes.', $code);

        $this->notifications->createNotification($user, $this->notificationType($channel), [
            'title' => $title,
            'body' => $body,
            'data' => ['channel' => $channel, 'expires_at' => $expiresAt->toISOString()],
            'url' => '/profile/verification',
        ]);

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

        if ($channel === UserVerificationCode::CHANNEL_EMAIL) {
            $user->forceFill([
                'email_verified' => true,
                'email_verified_at' => now(),
            ])->save();
        } else {
            $user->update([
                'phone_verified' => true,
            ]);
        }

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

    private function notificationType(string $channel): string
    {
        return $channel === UserVerificationCode::CHANNEL_EMAIL
            ? 'verification.email'
            : 'verification.phone';
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

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return $phone;
        }
        $suffix = substr($digits, -2);
        $masked = str_repeat('*', max(strlen($digits) - 2, 0)).$suffix;

        return $masked;
    }
}
