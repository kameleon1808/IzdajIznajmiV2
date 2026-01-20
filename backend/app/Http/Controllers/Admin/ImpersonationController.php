<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function start(Request $request, User $user): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $isAdminTarget = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        abort_if($isAdminTarget, 403, 'Cannot impersonate another admin');

        $session = $request->session();
        $session->put('impersonator_id', $admin->id);
        $session->put('impersonated_id', $user->id);

        Auth::guard('web')->login($user);
        $session->regenerate();

        $this->auditLog->record($admin->id, 'impersonation.start', User::class, $user->id, [
            'impersonator_id' => $admin->id,
        ]);

        return response()->json([
            'user' => new UserResource($user->load('roles')),
            'impersonating' => true,
            'impersonator' => new UserResource($admin->loadMissing('roles')),
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $session = $request->session();
        $impersonatorId = $session->pull('impersonator_id');
        $impersonatedId = $session->pull('impersonated_id');

        if (!$impersonatorId) {
            return response()->json(['message' => 'No impersonation active']);
        }

        $admin = User::find($impersonatorId);
        if ($admin) {
            Auth::guard('web')->login($admin);
            $session->regenerate();
            $this->auditLog->record($admin->id, 'impersonation.stop', User::class, $impersonatedId);
        } else {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();
        }

        return response()->json([
            'user' => $admin ? new UserResource($admin->loadMissing('roles')) : null,
            'impersonating' => false,
        ]);
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        abort_unless($isAdmin, 403, 'Forbidden');

        return $user;
    }
}
