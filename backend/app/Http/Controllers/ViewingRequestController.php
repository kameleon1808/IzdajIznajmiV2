<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreViewingRequestRequest;
use App\Http\Resources\ViewingRequestResource;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Models\ViewingSlot;
use App\Services\ListingStatusService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ViewingRequestController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function store(StoreViewingRequestRequest $request, ViewingSlot $viewingSlot): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Only seekers can request a viewing');

        $viewingSlot->loadMissing('listing');
        $listing = $viewingSlot->listing;
        abort_if(! $listing, 404, 'Listing not found for slot');

        if ($listing->status !== ListingStatusService::STATUS_ACTIVE) {
            return response()->json(['message' => 'Listing is not active for viewings'], 422);
        }

        $isRecurring = in_array($viewingSlot->pattern, ['weekends', 'weekdays', 'everyday', 'custom'], true);
        if (! $viewingSlot->is_active || (! $isRecurring && $viewingSlot->ends_at <= now())) {
            return response()->json(['message' => 'Viewing slot is not available'], 422);
        }

        if ((int) $listing->owner_id === (int) $user->id) {
            return response()->json(['message' => 'You cannot request a viewing for your own listing'], 422);
        }

        $payload = $request->validated();
        $scheduledAt = Carbon::parse($payload['scheduled_at']);
        $scheduleError = $this->validateScheduledAt($viewingSlot, $scheduledAt);
        if ($scheduleError) {
            return response()->json(['message' => $scheduleError], 422);
        }

        try {
            $viewingRequest = DB::transaction(function () use ($viewingSlot, $listing, $user, $payload, $scheduledAt) {
                $slot = ViewingSlot::where('id', $viewingSlot->id)->lockForUpdate()->firstOrFail();

                $activeRequests = ViewingRequest::where('viewing_slot_id', $slot->id)
                    ->whereIn('status', [ViewingRequest::STATUS_REQUESTED, ViewingRequest::STATUS_CONFIRMED])
                    ->lockForUpdate()
                    ->count();

                if ($activeRequests >= $slot->capacity) {
                    throw new \RuntimeException('Slot already taken');
                }

                return ViewingRequest::create([
                    'listing_id' => $listing->id,
                    'viewing_slot_id' => $slot->id,
                    'seeker_id' => $user->id,
                    'landlord_id' => $slot->landlord_id,
                    'status' => ViewingRequest::STATUS_REQUESTED,
                    'message' => $payload['message'] ?? null,
                    'scheduled_at' => $scheduledAt,
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Viewing slot is already booked'], 422);
        }

        $viewingRequest->load(['slot', 'listing.images']);
        $landlord = $listing->owner ?? User::find($listing->owner_id);

        if ($landlord) {
            $slotTime = $viewingRequest->scheduled_at ?? $viewingRequest->slot?->starts_at;
            $this->notifications->createNotification($landlord, Notification::TYPE_VIEWING_REQUESTED, [
                'title' => 'New viewing request',
                'body' => sprintf(
                    '%s requested a viewing for "%s" on %s.',
                    $user->name ?? 'A seeker',
                    $listing->title,
                    $slotTime?->toDayDateTimeString() ?? 'a selected time'
                ),
                'data' => [
                    'viewing_request_id' => $viewingRequest->id,
                    'listing_id' => $listing->id,
                    'slot_id' => $viewingSlot->id,
                    'status' => $viewingRequest->status,
                ],
                'url' => $this->viewingDeepLink($viewingRequest->id),
            ]);
        }

        return response()->json(new ViewingRequestResource($viewingRequest), 201);
    }

    public function seekerIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Forbidden');

        $requests = ViewingRequest::with(['slot', 'listing.images'])
            ->where('seeker_id', $user->id)
            ->latest()
            ->get();

        return response()->json(ViewingRequestResource::collection($requests));
    }

    public function landlordIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Forbidden');

        $landlordId = $this->userHasRole($user, 'admin') && $request->filled('landlordId')
            ? (int) $request->input('landlordId')
            : $user->id;

        $listingId = $request->input('listing_id');

        $requests = ViewingRequest::with(['slot', 'listing.images'])
            ->where('landlord_id', $landlordId)
            ->when($listingId, fn ($q) => $q->where('listing_id', (int) $listingId))
            ->latest()
            ->get();

        return response()->json(ViewingRequestResource::collection($requests));
    }

    public function confirm(Request $request, ViewingRequest $viewingRequest): JsonResponse
    {
        Gate::authorize('updateStatus', [$viewingRequest, ViewingRequest::STATUS_CONFIRMED]);

        try {
            $viewingRequest = DB::transaction(function () use ($viewingRequest) {
                $slot = ViewingSlot::where('id', $viewingRequest->viewing_slot_id)->lockForUpdate()->first();
                if (! $slot) {
                    throw new \RuntimeException('Slot missing');
                }

                $confirmedCount = ViewingRequest::where('viewing_slot_id', $slot->id)
                    ->where('status', ViewingRequest::STATUS_CONFIRMED)
                    ->where('id', '!=', $viewingRequest->id)
                    ->lockForUpdate()
                    ->count();

                if ($confirmedCount >= $slot->capacity) {
                    throw new \RuntimeException('Slot already confirmed');
                }

                $viewingRequest->update([
                    'status' => ViewingRequest::STATUS_CONFIRMED,
                    'cancelled_by' => null,
                ]);

                return $viewingRequest->fresh(['slot', 'listing.images']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Viewing slot already confirmed'], 422);
        }

        $seeker = $viewingRequest->seeker ?? User::find($viewingRequest->seeker_id);
        $listing = $viewingRequest->listing ?? Listing::find($viewingRequest->listing_id);
        if ($seeker && $listing) {
            $this->notifications->createNotification($seeker, Notification::TYPE_VIEWING_CONFIRMED, [
                'title' => 'Viewing confirmed',
                'body' => sprintf('Your viewing for "%s" is confirmed.', $listing->title),
                'data' => [
                    'viewing_request_id' => $viewingRequest->id,
                    'listing_id' => $listing->id,
                    'status' => $viewingRequest->status,
                ],
                'url' => $this->viewingDeepLink($viewingRequest->id),
            ]);
        }

        return response()->json(new ViewingRequestResource($viewingRequest));
    }

    public function reject(Request $request, ViewingRequest $viewingRequest): JsonResponse
    {
        Gate::authorize('updateStatus', [$viewingRequest, ViewingRequest::STATUS_REJECTED]);

        $viewingRequest->update([
            'status' => ViewingRequest::STATUS_REJECTED,
            'cancelled_by' => null,
        ]);

        $viewingRequest->load(['slot', 'listing.images']);
        $seeker = $viewingRequest->seeker ?? User::find($viewingRequest->seeker_id);
        $listing = $viewingRequest->listing;

        if ($seeker && $listing) {
            $this->notifications->createNotification($seeker, Notification::TYPE_VIEWING_CANCELLED, [
                'title' => 'Viewing rejected',
                'body' => sprintf('Your viewing request for "%s" was rejected.', $listing->title),
                'data' => [
                    'viewing_request_id' => $viewingRequest->id,
                    'listing_id' => $listing->id,
                    'status' => $viewingRequest->status,
                ],
                'url' => $this->viewingDeepLink($viewingRequest->id),
            ]);
        }

        return response()->json(new ViewingRequestResource($viewingRequest));
    }

    public function cancel(Request $request, ViewingRequest $viewingRequest): JsonResponse
    {
        Gate::authorize('updateStatus', [$viewingRequest, ViewingRequest::STATUS_CANCELLED]);

        $actor = $request->user();
        $cancelledBy = ViewingRequest::CANCELLED_BY_SYSTEM;

        if ($actor?->id === $viewingRequest->seeker_id) {
            $cancelledBy = ViewingRequest::CANCELLED_BY_SEEKER;
        } elseif ($actor?->id === $viewingRequest->landlord_id) {
            $cancelledBy = ViewingRequest::CANCELLED_BY_LANDLORD;
        }

        $viewingRequest->update([
            'status' => ViewingRequest::STATUS_CANCELLED,
            'cancelled_by' => $cancelledBy,
        ]);

        $viewingRequest->load(['slot', 'listing.images']);

        $recipient = $cancelledBy === ViewingRequest::CANCELLED_BY_SEEKER
            ? ($viewingRequest->landlord ?? User::find($viewingRequest->landlord_id))
            : ($viewingRequest->seeker ?? User::find($viewingRequest->seeker_id));

        if ($recipient && $viewingRequest->listing) {
            $this->notifications->createNotification($recipient, Notification::TYPE_VIEWING_CANCELLED, [
                'title' => 'Viewing cancelled',
                'body' => sprintf('Viewing for "%s" was cancelled.', $viewingRequest->listing->title),
                'data' => [
                    'viewing_request_id' => $viewingRequest->id,
                    'listing_id' => $viewingRequest->listing->id,
                    'status' => $viewingRequest->status,
                ],
                'url' => $this->viewingDeepLink($viewingRequest->id),
            ]);
        }

        return response()->json(new ViewingRequestResource($viewingRequest));
    }

    public function ics(Request $request, ViewingRequest $viewingRequest)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        if ($viewingRequest->status !== ViewingRequest::STATUS_CONFIRMED) {
            abort(404);
        }

        $isParticipant = in_array($user->id, [$viewingRequest->seeker_id, $viewingRequest->landlord_id], true)
            || $this->userHasRole($user, 'admin');

        abort_unless($isParticipant, 403, 'Forbidden');

        $viewingRequest->loadMissing(['slot', 'listing']);

        $slot = $viewingRequest->slot;
        $listing = $viewingRequest->listing;
        if (! $slot || ! $listing) {
            abort(404);
        }

        $starts = $slot->starts_at instanceof Carbon ? $slot->starts_at : Carbon::parse($slot->starts_at);
        $ends = $slot->ends_at instanceof Carbon ? $slot->ends_at : Carbon::parse($slot->ends_at);

        if ($viewingRequest->scheduled_at) {
            $starts = $viewingRequest->scheduled_at instanceof Carbon
                ? $viewingRequest->scheduled_at
                : Carbon::parse($viewingRequest->scheduled_at);
            $ends = $this->resolveScheduledEnd($starts, $slot);
        }

        $ics = $this->buildIcs($viewingRequest, $listing, $starts, $ends);

        $filename = sprintf('viewing-%d.ics', $viewingRequest->id);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildIcs(ViewingRequest $viewingRequest, Listing $listing, Carbon $starts, Carbon $ends): string
    {
        $summary = sprintf('Viewing: %s', $listing->title);
        $location = trim(sprintf('%s %s %s', $listing->address ?? '', $listing->city ?? '', $listing->country ?? ''));
        $uid = sprintf('viewing-%d@izdaj-iznajmi', $viewingRequest->id);
        $dtStamp = now()->utc()->format('Ymd\THis\Z');
        $dtStart = $starts->utc()->format('Ymd\THis\Z');
        $dtEnd = $ends->utc()->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IzdajIznajmi//Viewings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$dtStamp}",
            "DTSTART:{$dtStart}",
            "DTEND:{$dtEnd}",
            'SUMMARY:'.$this->escapeIcsText($summary),
            'DESCRIPTION:'.$this->escapeIcsText('Viewing appointment'),
            'LOCATION:'.$this->escapeIcsText($location),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private function escapeIcsText(string $text): string
    {
        return addcslashes($text, ',;\\');
    }

    private function validateScheduledAt(ViewingSlot $slot, Carbon $scheduledAt): ?string
    {
        $slotStart = $slot->starts_at instanceof Carbon ? $slot->starts_at : Carbon::parse($slot->starts_at);
        $slotEnd = $slot->ends_at instanceof Carbon ? $slot->ends_at : Carbon::parse($slot->ends_at);

        $selectedDate = $scheduledAt->copy()->startOfDay();
        $startDate = $slotStart->copy()->startOfDay();
        $endDate = $slotEnd->copy()->startOfDay();
        $isRecurring = in_array($slot->pattern, ['weekends', 'weekdays', 'everyday', 'custom'], true);
        $hasRangeEnd = $endDate->diffInDays($startDate) >= 1;

        if (! $isRecurring) {
            if ($selectedDate->lt($startDate) || $selectedDate->gt($endDate)) {
                return 'Selected date is outside slot availability';
            }
        } else {
            if ($selectedDate->lt($startDate)) {
                return 'Selected date is outside slot availability';
            }
            if ($hasRangeEnd && $selectedDate->gt($endDate)) {
                return 'Selected date is outside slot availability';
            }
        }

        if ($slot->pattern === 'weekends' && $scheduledAt->isWeekday()) {
            return 'Selected date must be on a weekend';
        }

        if ($slot->pattern === 'weekdays' && $scheduledAt->isWeekend()) {
            return 'Selected date must be on a weekday';
        }

        if ($slot->pattern === 'custom') {
            $allowedDays = $slot->days_of_week ?? [];
            if (! in_array($scheduledAt->dayOfWeek, $allowedDays, true)) {
                return 'Selected date is not available for this slot';
            }
        }

        if ($slot->time_from && $slot->time_to) {
            $fromMinutes = $this->parseTimeToMinutes($slot->time_from);
            $toMinutes = $this->parseTimeToMinutes($slot->time_to);
            if ($fromMinutes === null || $toMinutes === null || $toMinutes < $fromMinutes) {
                return 'Slot time window is invalid';
            }
            $selectedMinutes = ((int) $scheduledAt->format('H')) * 60 + (int) $scheduledAt->format('i');
            if ($selectedMinutes < $fromMinutes || $selectedMinutes > $toMinutes) {
                return 'Selected time is outside slot hours';
            }
        } else {
            if ($scheduledAt->lt($slotStart) || $scheduledAt->gt($slotEnd)) {
                return 'Selected time is outside slot availability';
            }
        }

        return null;
    }

    private function parseTimeToMinutes(?string $time): ?int
    {
        if (! $time || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }
        [$hours, $minutes] = array_map('intval', explode(':', $time, 2));
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function resolveScheduledEnd(Carbon $starts, ?ViewingSlot $slot): Carbon
    {
        $end = $starts->copy()->addMinutes(60);
        if (! $slot) {
            return $end;
        }

        if ($slot->time_to) {
            $timeToMinutes = $this->parseTimeToMinutes($slot->time_to);
            if ($timeToMinutes !== null) {
                $timeTo = $starts->copy()->setTime(intdiv($timeToMinutes, 60), $timeToMinutes % 60, 0);
                if ($timeTo->gt($starts) && $timeTo->lt($end)) {
                    $end = $timeTo;
                }
            }
        } elseif ($slot->ends_at) {
            $slotEnd = $slot->ends_at instanceof Carbon ? $slot->ends_at : Carbon::parse($slot->ends_at);
            if ($slotEnd->gt($starts) && $slotEnd->lt($end)) {
                $end = $slotEnd;
            }
        }

        return $end;
    }

    private function viewingDeepLink(int $requestId): string
    {
        return sprintf('/bookings?tab=viewings&viewingRequestId=%d', $requestId);
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
