<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminRatingResource;
use App\Models\Rating;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingAdminController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = Rating::with(['rater:id,name,full_name', 'ratee:id,name,full_name'])
            ->withCount('reports')
            ->latest();

        if ($request->boolean('reported')) {
            $query->whereHas('reports');
        }

        $ratings = $query->paginate(15);

        return AdminRatingResource::collection($ratings)->response();
    }

    public function show(Request $request, Rating $rating): JsonResponse
    {
        $this->authorizeAdmin($request);
        $rating->load(['rater:id,name,full_name', 'ratee:id,name,full_name'])->loadCount('reports');

        return response()->json(new AdminRatingResource($rating));
    }

    public function destroy(Request $request, Rating $rating): JsonResponse
    {
        $this->authorizeAdmin($request);
        $rating->delete();

        $this->auditLog->record($request->user()->id, 'admin.rating.delete', Rating::class, $rating->id);

        return response()->json(['message' => 'Deleted']);
    }

    public function flagUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'is_suspicious' => ['required', 'boolean'],
        ]);

        $user->is_suspicious = $data['is_suspicious'];
        $user->save();

        $this->auditLog->record($request->user()->id, 'admin.user.flag_suspicious', User::class, $user->id, [
            'is_suspicious' => $data['is_suspicious'],
        ]);

        return response()->json(['message' => 'Updated', 'isSuspicious' => $user->is_suspicious]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';
        abort_unless($isAdmin, 403, 'Forbidden');
    }
}
