<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\KycSubmissionResource;
use App\Models\KycSubmission;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KycSubmissionAdminController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $allowed = [
            KycSubmission::STATUS_PENDING,
            KycSubmission::STATUS_APPROVED,
            KycSubmission::STATUS_REJECTED,
            KycSubmission::STATUS_WITHDRAWN,
        ];

        $submissions = KycSubmission::with([
            'user:id,full_name,name,email,landlord_verification_status,landlord_verified_at',
            'reviewer:id,full_name,name',
            'documents',
        ])
            ->when($status && in_array($status, $allowed, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json(KycSubmissionResource::collection($submissions));
    }

    public function show(KycSubmission $submission): JsonResponse
    {
        $submission->load([
            'user:id,full_name,name,email,landlord_verification_status,landlord_verified_at',
            'reviewer:id,full_name,name',
            'documents',
        ]);

        return response()->json(new KycSubmissionResource($submission));
    }

    public function approve(Request $request, KycSubmission $submission): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin, 401, 'Unauthenticated');

        if ($submission->status !== KycSubmission::STATUS_PENDING) {
            return response()->json(['message' => 'Submission is not pending.'], 422);
        }

        $note = $request->input('note');

        DB::transaction(function () use ($submission, $admin, $note) {
            $submission->update([
                'status' => KycSubmission::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewer_id' => $admin->id,
                'reviewer_note' => $note,
            ]);

            $submission->user?->update([
                'landlord_verification_status' => 'approved',
                'landlord_verified_at' => now(),
                'landlord_verification_notes' => $note,
            ]);
        });

        $landlord = $submission->user;
        if ($landlord) {
            $this->notifications->createNotification($landlord, Notification::TYPE_KYC_APPROVED, [
                'title' => 'Verification approved',
                'body' => 'Your landlord verification is approved.',
                'data' => ['submission_id' => $submission->id],
                'url' => '/profile/verification',
            ]);
        }

        return response()->json(new KycSubmissionResource($submission->fresh('documents', 'user', 'reviewer')));
    }

    public function reject(Request $request, KycSubmission $submission): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin, 401, 'Unauthenticated');

        if ($submission->status !== KycSubmission::STATUS_PENDING) {
            return response()->json(['message' => 'Submission is not pending.'], 422);
        }

        $note = $request->input('note');

        DB::transaction(function () use ($submission, $admin, $note) {
            $submission->update([
                'status' => KycSubmission::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewer_id' => $admin->id,
                'reviewer_note' => $note,
            ]);

            $submission->user?->update([
                'landlord_verification_status' => 'rejected',
                'landlord_verified_at' => null,
                'landlord_verification_notes' => $note,
            ]);
        });

        $landlord = $submission->user;
        if ($landlord) {
            $this->notifications->createNotification($landlord, Notification::TYPE_KYC_REJECTED, [
                'title' => 'Verification rejected',
                'body' => $note ? "Your verification was rejected: {$note}" : 'Your verification was rejected. Please resubmit.',
                'data' => ['submission_id' => $submission->id],
                'url' => '/profile/verification',
            ]);
        }

        return response()->json(new KycSubmissionResource($submission->fresh('documents', 'user', 'reviewer')));
    }

    public function redact(Request $request, KycSubmission $submission): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin, 401, 'Unauthenticated');

        $note = $request->input('note') ?? 'Redacted by admin';

        DB::transaction(function () use ($submission, $admin, $note) {
            $this->deleteDocuments($submission);

            $submission->update([
                'status' => KycSubmission::STATUS_WITHDRAWN,
                'reviewed_at' => now(),
                'reviewer_id' => $admin->id,
                'reviewer_note' => $note,
            ]);

            $submission->user?->update([
                'landlord_verification_status' => 'none',
                'landlord_verified_at' => null,
                'landlord_verification_notes' => $note,
            ]);
        });

        return response()->json(new KycSubmissionResource($submission->fresh('documents', 'user', 'reviewer')));
    }

    private function deleteDocuments(KycSubmission $submission): void
    {
        $submission->loadMissing('documents');
        foreach ($submission->documents as $document) {
            if ($document->path) {
                Storage::disk($document->disk ?? 'private')->delete($document->path);
            }
        }
        $submission->documents()->delete();
    }
}
