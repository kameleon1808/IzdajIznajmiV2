<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreViewingSlotRequest;
use App\Http\Requests\UpdateViewingSlotRequest;
use App\Http\Resources\ViewingSlotResource;
use App\Models\Listing;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Services\ListingStatusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ViewingSlotController extends Controller
{
    public function index(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $isOwner = $this->isOwnerOrAdmin($user, $listing);

        $query = ViewingSlot::where('listing_id', $listing->id)->orderBy('starts_at');

        if (! $isOwner) {
            $query->where('is_active', true)->where('ends_at', '>', now());
        }

        $slots = $query->get();

        return response()->json(ViewingSlotResource::collection($slots));
    }

    public function store(StoreViewingSlotRequest $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->isOwnerOrAdmin($user, $listing), 403, 'Forbidden');

        if ($listing->status !== ListingStatusService::STATUS_ACTIVE) {
            return response()->json(['message' => 'Listing must be active to schedule viewings'], 422);
        }

        $data = $request->validated();
        $slot = ViewingSlot::create([
            'listing_id' => $listing->id,
            'landlord_id' => $listing->owner_id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'capacity' => $data['capacity'] ?? 1,
            'is_active' => $data['is_active'] ?? true,
            'pattern' => $data['pattern'] ?? null,
            'days_of_week' => $data['days_of_week'] ?? null,
            'time_from' => $data['time_from'] ?? null,
            'time_to' => $data['time_to'] ?? null,
        ]);

        return response()->json(new ViewingSlotResource($slot), 201);
    }

    public function update(UpdateViewingSlotRequest $request, ViewingSlot $viewingSlot): JsonResponse
    {
        Gate::authorize('update', $viewingSlot);

        $listing = $viewingSlot->listing;
        if ($listing && $listing->status !== ListingStatusService::STATUS_ACTIVE) {
            return response()->json(['message' => 'Listing must be active to modify viewings'], 422);
        }

        $data = $request->validated();

        $nextStartsAt = $data['starts_at'] ?? $viewingSlot->starts_at;
        $nextEndsAt = $data['ends_at'] ?? $viewingSlot->ends_at;

        if ($nextStartsAt && ! $nextStartsAt instanceof Carbon) {
            $nextStartsAt = Carbon::parse($nextStartsAt);
        }

        if ($nextEndsAt && ! $nextEndsAt instanceof Carbon) {
            $nextEndsAt = Carbon::parse($nextEndsAt);
        }

        if ($nextEndsAt && $nextStartsAt && $nextEndsAt <= $nextStartsAt) {
            return response()->json(['message' => 'End time must be after start time'], 422);
        }

        if (array_key_exists('capacity', $data)) {
            $activeCount = ViewingRequest::where('viewing_slot_id', $viewingSlot->id)
                ->whereIn('status', [ViewingRequest::STATUS_REQUESTED, ViewingRequest::STATUS_CONFIRMED])
                ->count();

            if ((int) $data['capacity'] < $activeCount) {
                return response()->json(['message' => 'Capacity cannot be lower than active requests'], 422);
            }
        }

        $viewingSlot->update($data);

        return response()->json(new ViewingSlotResource($viewingSlot->fresh()));
    }

    public function destroy(Request $request, ViewingSlot $viewingSlot): JsonResponse
    {
        Gate::authorize('delete', $viewingSlot);

        $hasActiveRequests = ViewingRequest::where('viewing_slot_id', $viewingSlot->id)
            ->whereIn('status', [ViewingRequest::STATUS_REQUESTED, ViewingRequest::STATUS_CONFIRMED])
            ->exists();

        if ($hasActiveRequests) {
            return response()->json(['message' => 'Cannot delete slot with active requests'], 422);
        }

        $viewingSlot->delete();

        return response()->json()->setStatusCode(204);
    }

    private function isOwnerOrAdmin($user, Listing $listing): bool
    {
        $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin')) || $user->role === 'admin';

        return $isAdmin || $listing->owner_id === $user->id;
    }
}
