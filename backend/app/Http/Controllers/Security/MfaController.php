<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\FraudSignalService;
use App\Services\MfaService;
use App\Services\SecuritySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MfaController extends Controller
{
    public function __construct(
        private MfaService $mfa,
        private SecuritySessionService $sessions,
        private FraudSignalService $fraudSignals
    ) {}

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        if ($user->mfa_enabled) {
            return response()->json(['message' => 'MFA is already enabled.'], 409);
        }

        $payload = $this->mfa->setup($user);

        return response()->json([
            'secret' => $payload['secret'],
            'otpauth_url' => $payload['otpauth_url'],
            'qr_svg' => $payload['qr_svg'],
            'recovery_codes' => $payload['recovery_codes'],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $this->mfa->confirm($user, $data['code'])) {
            return response()->json(['message' => 'Invalid MFA code.'], 422);
        }

        $request->session()->forget('mfa_pending');
        $request->session()->put('mfa_verified_at', now()->toISOString());

        return response()->json(['user' => new UserResource($user->fresh('roles'))]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'challenge_id' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'remember_device' => ['sometimes', 'boolean'],
            'device_label' => ['nullable', 'string'],
        ]);

        if (! $request->session()->get('mfa_pending')) {
            return response()->json(['message' => 'No MFA challenge pending.'], 409);
        }

        if ($request->session()->get('mfa_challenge_id') !== $data['challenge_id']) {
            return response()->json(['message' => 'Invalid MFA challenge.'], 422);
        }

        $valid = false;
        if (! empty($data['code'])) {
            $valid = $this->mfa->verifyTotp($user, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->mfa->verifyRecoveryCode($user, $data['recovery_code']);
        }

        if (! $valid) {
            $this->fraudSignals->recordFailedMfaAttempt($user);

            return response()->json(['message' => 'Invalid MFA code.'], 422);
        }

        $request->session()->forget('mfa_pending');
        $request->session()->forget('mfa_challenge_id');
        $request->session()->put('mfa_verified_at', now()->toISOString());

        if (! empty($data['remember_device'])) {
            $this->sessions->rememberDevice($user, $request, $data['device_label'] ?? null);
        }

        return response()->json(['user' => new UserResource($user->fresh('roles'))]);
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid password.'], 422);
        }

        $valid = false;
        if (! empty($data['code'])) {
            $valid = $this->mfa->verifyTotp($user, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->mfa->verifyRecoveryCode($user, $data['recovery_code']);
        }

        if (! $valid) {
            return response()->json(['message' => 'Invalid MFA code.'], 422);
        }

        $this->mfa->disable($user);
        $request->session()->forget(['mfa_pending', 'mfa_challenge_id', 'mfa_verified_at']);

        return response()->json(['message' => 'MFA disabled.']);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $valid = false;
        if (! empty($data['code'])) {
            $valid = $this->mfa->verifyTotp($user, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->mfa->verifyRecoveryCode($user, $data['recovery_code']);
        }

        if (! $valid) {
            return response()->json(['message' => 'Invalid MFA code.'], 422);
        }

        $codes = $this->mfa->regenerateRecoveryCodes($user);

        return response()->json(['recovery_codes' => $codes]);
    }
}
