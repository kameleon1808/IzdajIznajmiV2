<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = User::query();
        $search = trim((string) $request->input('q'));
        $role = $request->input('role');
        $suspicious = $request->input('suspicious');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($suspicious !== null) {
            $query->where('is_suspicious', filter_var($suspicious, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->latest()->limit(250)->get();

        return response()->json(UserResource::collection($users));
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($user->hasRole('admin') || $user->role === 'admin', 403, 'Forbidden');

        return $user;
    }
}
