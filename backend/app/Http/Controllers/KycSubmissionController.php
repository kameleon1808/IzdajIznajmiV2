<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKycSubmissionRequest;
use App\Http\Resources\KycSubmissionResource;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycSubmissionController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function store(StoreKycSubmissionRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord']), 403, 'Only landlords can submit KYC.');

        $hasPending = KycSubmission::where('user_id', $user->id)
            ->where('status', KycSubmission::STATUS_PENDING)
            ->exists();
        if ($hasPending) {
            return response()->json(['message' => 'You already have a pending submission.'], 409);
        }

        $submission = DB::transaction(function () use ($request, $user) {
            $submission = KycSubmission::create([
                'user_id' => $user->id,
                'status' => KycSubmission::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            $user->update([
                'landlord_verification_status' => 'pending',
                'landlord_verified_at' => null,
                'landlord_verification_notes' => null,
            ]);

            $this->storeDocuments($request, $submission);

            return $submission;
        });

        $this->notifications->createNotification($user, Notification::TYPE_KYC_SUBMISSION_RECEIVED, [
            'title' => 'Verification submitted',
            'body' => 'Your verification documents have been received and are under review.',
            'data' => ['submission_id' => $submission->id],
            'url' => '/profile/verification',
        ]);

        $submission->load('documents');

        return response()->json(new KycSubmissionResource($submission), 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Forbidden');

        $submission = KycSubmission::with('documents')
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->first();

        return response()->json($submission ? new KycSubmissionResource($submission) : null);
    }

    public function withdraw(Request $request, KycSubmission $submission): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($submission->user_id === $user->id, 403, 'Forbidden');

        if ($submission->status !== KycSubmission::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending submissions can be withdrawn.'], 422);
        }

        DB::transaction(function () use ($submission, $user) {
            $this->deleteDocuments($submission);

            $submission->update([
                'status' => KycSubmission::STATUS_WITHDRAWN,
                'reviewed_at' => now(),
                'reviewer_id' => null,
                'reviewer_note' => null,
            ]);

            if ($user->landlord_verification_status === 'pending') {
                $user->update([
                    'landlord_verification_status' => 'none',
                    'landlord_verified_at' => null,
                    'landlord_verification_notes' => null,
                ]);
            }
        });

        return response()->json(new KycSubmissionResource($submission->load('documents')));
    }

    private function storeDocuments(StoreKycSubmissionRequest $request, KycSubmission $submission): void
    {
        $userId = $submission->user_id;
        $disk = 'private';
        $dir = "kyc/{$userId}/{$submission->id}";

        $files = [
            KycDocument::TYPE_ID_FRONT => $request->file('id_front'),
            KycDocument::TYPE_ID_BACK => $request->file('id_back'),
            KycDocument::TYPE_SELFIE => $request->file('selfie'),
            KycDocument::TYPE_PROOF => $request->file('proof_of_address'),
        ];

        foreach ($files as $type => $file) {
            if (! $file) {
                continue;
            }

            $extension = $file->getClientOriginalExtension();
            $filename = $type.'_'.Str::uuid()->toString().($extension ? ".{$extension}" : '');
            $path = $file->storeAs($dir, $filename, $disk);

            KycDocument::create([
                'submission_id' => $submission->id,
                'user_id' => $userId,
                'doc_type' => $type,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'disk' => $disk,
                'path' => $path,
            ]);
        }
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

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
