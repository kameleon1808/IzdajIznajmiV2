<?php

namespace App\Services\Verification;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Services\Verification\Exceptions\VerificationDeliveryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailVerificationCodeSender
{
    public function send(User $user, string $code): void
    {
        if (! $user->email) {
            throw new VerificationDeliveryException('Email address is missing.');
        }

        if (! app()->environment(['local', 'testing']) && config('mail.default') === 'log') {
            throw new VerificationDeliveryException('Email delivery is not configured.');
        }

        $ttlMinutes = (int) config('verification.code_ttl_minutes', 10);

        try {
            Mail::to($user->email)->send(new VerificationCodeMail($code, $ttlMinutes));
        } catch (Throwable $e) {
            Log::error('verification_email_send_failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            throw new VerificationDeliveryException('Unable to send verification email.', previous: $e);
        }
    }
}
