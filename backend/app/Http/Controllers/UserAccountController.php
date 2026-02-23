<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SecuritySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserAccountController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private SecuritySessionService $sessions
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
            'address_book' => ['sometimes', 'nullable', 'array'],
        ]);

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
}
