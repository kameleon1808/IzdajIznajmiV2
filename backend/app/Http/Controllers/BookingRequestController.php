<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequestRequest;
use App\Http\Requests\UpdateBookingRequestStatusRequest;
use App\Http\Resources\BookingRequestResource;
use App\Models\BookingRequest;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingRequestController extends Controller
{
    public function store(StoreBookingRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($user->role === 'tenant' || $user->role === 'admin', 403, 'Only tenants can request booking');

        $data = $request->validated();
        $listing = Listing::findOrFail($data['listingId']);

        if ((int) $listing->owner_id !== (int) $data['landlordId']) {
            return response()->json(['message' => 'Landlord does not own listing'], 422);
        }

        $bookingRequest = BookingRequest::create([
            'listing_id' => $listing->id,
            'tenant_id' => $user->id,
            'landlord_id' => $listing->owner_id,
            'start_date' => $data['startDate'] ?? null,
            'end_date' => $data['endDate'] ?? null,
            'guests' => $data['guests'],
            'message' => $data['message'],
            'status' => 'pending',
        ]);

        return response()->json(new BookingRequestResource($bookingRequest), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $role = $request->query('role');
        if ($role === 'tenant') {
            abort_unless($user->role === 'tenant' || $user->role === 'admin', 403, 'Forbidden');
            $requests = BookingRequest::where('tenant_id', $user->id)->latest()->get();
        } elseif ($role === 'landlord') {
            abort_unless(in_array($user->role, ['landlord', 'admin']), 403, 'Forbidden');
            $landlordId = $user->role === 'admin' && $request->filled('landlordId') ? (int) $request->input('landlordId') : $user->id;
            $requests = BookingRequest::where('landlord_id', $landlordId)->latest()->get();
        } else {
            return response()->json(['message' => 'role query param required (tenant|landlord)'], 422);
        }

        return response()->json(BookingRequestResource::collection($requests));
    }

    public function updateStatus(UpdateBookingRequestStatusRequest $request, BookingRequest $bookingRequest): JsonResponse
    {
        $newStatus = $request->validated()['status'];
        Gate::authorize('updateStatus', [$bookingRequest, $newStatus]);

        if (!in_array($bookingRequest->status, ['pending']) && auth()->user()?->role !== 'admin') {
            return response()->json(['message' => 'Cannot change finalized request'], 422);
        }

        $bookingRequest->update(['status' => $newStatus]);

        return response()->json(new BookingRequestResource($bookingRequest));
    }
}
