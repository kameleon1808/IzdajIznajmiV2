<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\KycSubmissionResource;
use App\Models\AuditLog;
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
            KycSubmission::STATUS_QUARANTINED,
        ];

        $submissions = KycSubmission::with([
            'user:id,full_name,name,email,verification_status,verified_at',
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
            'user:id,full_name,name,email,verification_status,verified_at',
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
        $retentionDays = (int) config('kyc.document_retention_days', 90);

        DB::transaction(function () use ($submission, $admin, $note, $retentionDays) {
            $submission->update([
                'status' => KycSubmission::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewer_id' => $admin->id,
                'reviewer_note' => $note,
                'purge_after' => now()->addDays($retentionDays),
            ]);

            $submission->user?->update([
                'verification_status' => 'approved',
                'verified_at' => now(),
                'verification_notes' => $note,
                'address_verified' => true,
            ]);
        });

        $landlord = $submission->user;
        if ($landlord) {
            $this->notifications->createNotification($landlord, Notification::TYPE_KYC_APPROVED, [
                'title' => 'Verification approved',
                'body' => 'Your verification is approved.',
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
        $retentionDays = (int) config('kyc.document_retention_days', 90);

        DB::transaction(function () use ($submission, $admin, $note, $retentionDays) {
            $submission->update([
                'status' => KycSubmission::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewer_id' => $admin->id,
                'reviewer_note' => $note,
                'purge_after' => now()->addDays($retentionDays),
            ]);

            $submission->user?->update([
                'verification_status' => 'rejected',
                'verified_at' => null,
                'verification_notes' => $note,
                'address_verified' => false,
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
                // Documents are already deleted — no purge_after needed
                'purge_after' => null,
            ]);

            $submission->user?->update([
                'verification_status' => 'none',
                'verified_at' => null,
                'verification_notes' => $note,
                'address_verified' => false,
            ]);
        });

        return response()->json(new KycSubmissionResource($submission->fresh('documents', 'user', 'reviewer')));
    }

    public function auditLog(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 50), 200);

        $entries = AuditLog::with('actor:id,full_name,name,email')
            ->whereIn('action', ['kyc.document.admin_downloaded', 'kyc.document.owner_downloaded'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actorId' => $log->actor_user_id,
                'actorName' => $log->actor?->full_name ?? $log->actor?->name ?? "User #{$log->actor_user_id}",
                'actorEmail' => $log->actor?->email,
                'documentId' => $log->subject_id,
                'submissionId' => $log->metadata['submission_id'] ?? null,
                'ownerId' => $log->metadata['owner_id'] ?? null,
                'docType' => $log->metadata['doc_type'] ?? null,
                'isAdmin' => (bool) ($log->metadata['is_admin'] ?? false),
                'ipAddress' => $log->ip_address,
                'createdAt' => optional($log->created_at)->toISOString(),
            ]);

        return response()->json($entries);
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
