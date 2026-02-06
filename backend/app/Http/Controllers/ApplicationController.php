<?php

namespace App\Http\Controllers;

use App\Events\ApplicationCreated;
use App\Events\ApplicationStatusChanged;
use App\Http\Requests\ApplyToListingRequest;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Listing;
use App\Services\ListingStatusService;
use App\Services\FraudSignalService;
use App\Services\StructuredLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly StructuredLogger $log,
        private FraudSignalService $fraudSignals
    ) {
    }

    public function apply(ApplyToListingRequest $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Only seekers can apply');

        if ($listing->status !== ListingStatusService::STATUS_ACTIVE) {
            return response()->json(['message' => 'Listing is not active for applications'], 422);
        }

        if (Application::where('listing_id', $listing->id)->where('seeker_id', $user->id)->exists()) {
            return response()->json(['message' => 'You already applied to this listing'], 422);
        }

        try {
            $application = Application::create([
                'listing_id' => $listing->id,
                'seeker_id' => $user->id,
                'landlord_id' => $listing->owner_id,
                'message' => $request->validated()['message'] ?? null,
                'status' => Application::STATUS_SUBMITTED,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'You already applied to this listing'], 422);
            }

            throw $e;
        }

        event(new ApplicationCreated($application->load('listing')));

        $this->log->info('application_created', [
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'application_id' => $application->id,
            'status' => $application->status,
        ]);

        $this->recordRapidApplications($user);

        return response()->json(new ApplicationResource($application->load('listing.images')), 201);
    }

    public function seekerIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Forbidden');

        $applications = Application::with(['listing.images'])
            ->where('seeker_id', $user->id)
            ->latest()
            ->get();

        return response()->json(ApplicationResource::collection($applications));
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

        $applications = Application::with(['listing.images'])
            ->where('landlord_id', $landlordId)
            ->when($listingId, fn ($query) => $query->where('listing_id', (int) $listingId))
            ->latest()
            ->get();

        return response()->json(ApplicationResource::collection($applications));
    }

    public function update(UpdateApplicationStatusRequest $request, Application $application): JsonResponse
    {
        $status = $request->validated()['status'];
        Gate::authorize('updateStatus', [$application, $status]);

        $application->update(['status' => $status]);

        event(new ApplicationStatusChanged($application->fresh('listing')));

        $this->log->info('application_status_changed', [
            'application_id' => $application->id,
            'listing_id' => $application->listing_id,
            'user_id' => $request->user()?->id,
            'status' => $status,
        ]);

        return response()->json(new ApplicationResource($application->load('listing.images')));
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }

    private function recordRapidApplications($user): void
    {
        $settings = config('security.fraud.signals.rapid_applications', []);
        $threshold = (int) ($settings['threshold'] ?? 4);
        $windowMinutes = (int) ($settings['window_minutes'] ?? 30);
        $weight = (int) ($settings['weight'] ?? 10);

        $count = Application::where('seeker_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($count >= $threshold) {
            $this->fraudSignals->recordSignal(
                $user,
                'rapid_applications',
                $weight,
                ['count' => $count],
                $windowMinutes
            );
        }
    }
}
