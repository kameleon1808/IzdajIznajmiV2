<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\ChatAttachment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SecuritySessionService;
use App\Services\StructuredLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAccountController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private SecuritySessionService $sessions,
        private StructuredLogger $log
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(User::GENDERS)],
            'residential_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_status' => ['sometimes', 'nullable', Rule::in(User::EMPLOYMENT_STATUSES)],
            // Format-only validation; uniqueness is checked below via phone_hash
            // because the encrypted phone column cannot be queried by the DB directly.
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address_book' => ['sometimes', 'nullable', 'array'],
        ]);

        // Manually enforce phone uniqueness through the phone_hash index.
        if (array_key_exists('phone', $data) && $data['phone'] !== null) {
            $hash = User::hashPhone($data['phone']);
            $taken = User::where('phone_hash', $hash)->where('id', '!=', $user->id)->exists();
            if ($taken) {
                throw ValidationException::withMessages(['phone' => ['The phone number has already been taken.']]);
            }
        }

        $updates = [];
        if (array_key_exists('full_name', $data)) {
            $updates['full_name'] = $data['full_name'];
            $updates['name'] = $data['full_name'];
        }
        if (array_key_exists('date_of_birth', $data)) {
            $updates['date_of_birth'] = $data['date_of_birth'];
        }
        if (array_key_exists('gender', $data)) {
            $updates['gender'] = $data['gender'];
        }
        if (array_key_exists('residential_address', $data)) {
            $updates['residential_address'] = $data['residential_address'];
            if ($data['residential_address'] !== $user->residential_address) {
                $updates['address_verified'] = false;
            }
        }
        if (array_key_exists('employment_status', $data)) {
            $updates['employment_status'] = $data['employment_status'];
        }
        if (array_key_exists('phone', $data)) {
            $updates['phone'] = $data['phone'];
            $updates['phone_hash'] = User::hashPhone($data['phone']);
        }
        if (array_key_exists('address_book', $data)) {
            $updates['address_book'] = $data['address_book'];
        }

        if (! empty($updates)) {
            $user->fill($updates);
            $user->save();
        }

        return response()->json(['user' => new UserResource($user->fresh('roles'))]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $data['avatar'];
        $path = $file->store('avatars/'.$user->id, 'public');

        if ($user->avatar_path && $user->avatar_path !== $path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = $path;
        $user->save();

        return response()->json(['user' => new UserResource($user->fresh('roles'))]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        $sessionId = $request->session()->getId();
        if ($sessionId) {
            $this->sessions->revokeOtherSessions($user, $sessionId);
        }

        $this->auditLog->record($user->id, 'user.password.changed', User::class, $user->id);

        return response()->json(['message' => 'Password updated']);
    }

    /**
     * GDPR account deletion (DELETE /me).
     *
     * Anonymizes all PII fields in-place (does NOT hard-delete the row so that
     * referential integrity is preserved for transactions, ratings, contracts, etc.).
     * Removes associated files and revokes all active sessions.
     */
    public function deleteAccount(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => ['Password is incorrect.']]);
        }

        DB::transaction(function () use ($user, $request) {
            // 1. Delete avatar from public storage.
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            // 2. Delete chat attachment files uploaded by this user.
            ChatAttachment::where('uploader_id', $user->id)
                ->chunkById(100, function ($attachments) {
                    foreach ($attachments as $attachment) {
                        $disk = $attachment->disk ?? 'private';
                        if ($attachment->path_original) {
                            Storage::disk($disk)->delete($attachment->path_original);
                        }
                        if ($attachment->path_thumb) {
                            Storage::disk($disk)->delete($attachment->path_thumb);
                        }
                    }
                });

            // 3. Revoke all sessions.
            $this->sessions->revokeAllSessions($user);

            // 4. Delete push subscriptions.
            $user->pushSubscriptions()->delete();

            // 5. Anonymize PII fields — keep the row for referential integrity.
            $anonymizedEmail = 'deleted_'.$user->id.'@deleted.local';
            $user->fill([
                'name' => 'Deleted User',
                'full_name' => null,
                'email' => $anonymizedEmail,
                'phone' => null,
                'phone_hash' => null,
                'date_of_birth' => null,
                'gender' => null,
                'residential_address' => null,
                'employment_status' => null,
                'address_book' => null,
                'avatar_path' => null,
                'verification_notes' => null,
                'badge_override_json' => null,
                'mfa_totp_secret' => null,
                // Invalidate password so no future login is possible.
                'password' => Hash::make(bin2hex(random_bytes(32))),
            ]);
            $user->mfa_enabled = false;
            $user->save();

            // 6. Remove MFA recovery codes.
            $user->mfaRecoveryCodes()->delete();

            // 7. Audit trail.
            $this->auditLog->record(null, 'user.account.deleted', User::class, $user->id, [
                'actor_user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            $this->log->warning('auth.account_deleted', [
                'severity' => 'warning',
                'security_event' => true,
                'user_id' => $user->id,
            ]);
        });

        // Invalidate current session.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
