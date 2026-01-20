<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = $this->normalizeRole($data['role'] ?? 'seeker');
        $addressBook = $data['address_book'] ?? null;
        if (is_string($addressBook)) {
            $decoded = json_decode($addressBook, true);
            $addressBook = is_array($decoded) ? $decoded : null;
        }

        $user = User::create([
            'name' => $data['name'] ?? $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address_book' => $addressBook,
            'role' => $role,
            'password' => Hash::make($data['password']),
        ])->refresh();

        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($user->fresh('roles'))], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $request->session()->regenerate();

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json(['user' => new UserResource($user->load('roles'))]);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->forget(['impersonator_id', 'impersonated_id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = response()->noContent();
        $response->withCookie(Cookie::forget(config('session.cookie')));
        $response->withCookie(Cookie::forget('XSRF-TOKEN'));

        return $response;
    }

    public function me(Request $request): JsonResponse
    {
        $impersonatedId = $request->session()->get('impersonated_id');
        $user = $impersonatedId ? User::find($impersonatedId)?->load('roles') : $request->user()?->load('roles');
        $impersonatorId = $request->session()->get('impersonator_id');
        $impersonator = $impersonatorId ? User::find($impersonatorId)?->load('roles') : null;

        return response()->json([
            'user' => $user ? new UserResource($user) : null,
            'impersonating' => (bool) $impersonatorId,
            'impersonator' => $impersonator ? new UserResource($impersonator) : null,
        ]);
    }

    private function normalizeRole(string $role): string
    {
        $normalized = $role === 'tenant' ? 'seeker' : $role;

        return in_array($normalized, ['admin', 'landlord', 'seeker'], true) ? $normalized : 'seeker';
    }
}
