<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequestRequest;
use App\Http\Requests\UpdateBookingRequestStatusRequest;
use App\Http\Resources\BookingRequestResource;
use App\Models\BookingRequest;
use App\Models\Listing;
use App\Services\ListingStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingRequestController extends Controller
{
    public function store(StoreBookingRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Only seekers can request booking');

        $data = $request->validated();
        $listing = Listing::findOrFail($data['listingId']);

        if ((int) $listing->owner_id !== (int) $data['landlordId']) {
            return response()->json(['message' => 'Landlord does not own listing'], 422);
        }

        if ($listing->status !== ListingStatusService::STATUS_ACTIVE) {
            return response()->json(['message' => 'Listing is not available for booking'], 422);
        }

        $newStartDate = \Carbon\Carbon::parse($data['startDate'])->startOfDay();

        $conflicting = BookingRequest::where('listing_id', $listing->id)
            ->where('tenant_id', $user->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->whereNotNull('end_date')
            ->where('end_date', '>', now()->startOfDay())
            ->where('end_date', '>=', $newStartDate)
            ->exists();

        if ($conflicting) {
            return response()->json(['message' => 'You already have an active booking request for this period.'], 422);
        }

        $bookingRequest = BookingRequest::create([
            'listing_id' => $listing->id,
            'tenant_id' => $user->id,
            'landlord_id' => $listing->owner_id,
            'start_date' => $data['startDate'] ?? null,
            'end_date' => $data['endDate'] ?? null,
            'guests' => $data['guests'],
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(new BookingRequestResource($bookingRequest), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $role = $this->normalizeRole($request->query('role'));
        if ($role === 'seeker') {
            abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Forbidden');
            $requests = BookingRequest::where('tenant_id', $user->id)->latest()->get();
        } elseif ($role === 'landlord') {
            abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Forbidden');
            $landlordId = $this->userHasRole($user, 'admin') && $request->filled('landlordId')
                ? (int) $request->input('landlordId')
                : $user->id;
            $requests = BookingRequest::where('landlord_id', $landlordId)->latest()->get();
        } else {
            return response()->json(['message' => 'role query param required (seeker|landlord)'], 422);
        }

        return response()->json(BookingRequestResource::collection($requests));
    }

    public function updateStatus(UpdateBookingRequestStatusRequest $request, BookingRequest $bookingRequest): JsonResponse
    {
        $newStatus = $request->validated()['status'];
        Gate::authorize('updateStatus', [$bookingRequest, $newStatus]);

        if (! in_array($bookingRequest->status, ['pending']) && ! $this->userHasRole(auth()->user(), 'admin')) {
            return response()->json(['message' => 'Cannot change finalized request'], 422);
        }

        $bookingRequest->update(['status' => $newStatus]);

        return response()->json(new BookingRequestResource($bookingRequest));
    }

    private function normalizeRole(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        $normalized = $role === 'tenant' ? 'seeker' : $role;

        return in_array($normalized, ['seeker', 'landlord'], true) ? $normalized : null;
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
