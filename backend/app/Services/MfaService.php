<?php

namespace App\Services;

use App\Models\MfaRecoveryCode;
use App\Models\TrustedDevice;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use OTPHP\TOTP;

class MfaService
{
    public function setup(User $user): array
    {
        $totp = TOTP::create();
        $totp->setLabel($user->email ?? $user->name ?? 'user');
        $totp->setIssuer(config('security.mfa.issuer'));

        $secret = $totp->getSecret();
        $user->mfa_totp_secret = Crypt::encryptString($secret);
        $user->mfa_enabled = false;
        $user->mfa_confirmed_at = null;
        $user->save();

        $otpauth = $totp->getProvisioningUri();
        $qrSvg = $this->buildQrSvg($otpauth);
        $recoveryCodes = $this->regenerateRecoveryCodes($user);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauth,
            'qr_svg' => $qrSvg,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function confirm(User $user, string $code): bool
    {
        $secret = $this->getSecret($user);
        if (! $secret) {
            return false;
        }

        if (! $this->verifyCode($secret, $code)) {
            return false;
        }

        $user->mfa_enabled = true;
        $user->mfa_confirmed_at = now();
        $user->save();

        return true;
    }

    public function verifyTotp(User $user, string $code): bool
    {
        $secret = $this->getSecret($user);
        if (! $secret) {
            return false;
        }

        return $this->verifyCode($secret, $code);
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);
        if ($normalized === '') {
            return false;
        }

        $hash = hash('sha256', $normalized);
        $record = MfaRecoveryCode::where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (! $record) {
            return false;
        }

        $record->used_at = now();
        $record->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->mfa_enabled = false;
        $user->mfa_totp_secret = null;
        $user->mfa_confirmed_at = null;
        $user->save();

        MfaRecoveryCode::where('user_id', $user->id)->delete();
        TrustedDevice::where('user_id', $user->id)->delete();
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        MfaRecoveryCode::where('user_id', $user->id)->delete();

        $count = (int) config('security.mfa.recovery_codes', 8);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = Str::upper(Str::random(10));
            $code = substr($raw, 0, 5).'-'.substr($raw, 5);
            $normalized = $this->normalizeRecoveryCode($code);
            $codes[] = $code;

            MfaRecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => hash('sha256', $normalized),
                'used_at' => null,
                'created_at' => now(),
            ]);
        }

        return $codes;
    }

    public function getSecret(User $user): ?string
    {
        if (! $user->mfa_totp_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($user->mfa_totp_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    private function verifyCode(string $secret, string $code): bool
    {
        $totp = TOTP::create($secret);

        return $totp->verify($code);
    }

    private function buildQrSvg(string $data): string
    {
        $result = Builder::create()
            ->writer(new SvgWriter)
            ->data($data)
            ->size(220)
            ->margin(2)
            ->build();

        return $result->getString();
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return Str::of($code)
            ->upper()
            ->replace('-', '')
            ->replace(' ', '')
            ->toString();
    }
}
