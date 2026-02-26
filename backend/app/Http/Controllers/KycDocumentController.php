<?php

namespace App\Http\Controllers;

use App\Models\KycDocument;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycDocumentController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function show(Request $request, KycDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $isAdmin = $this->userHasRole($user, 'admin');
        $isOwner = $document->user_id === $user->id;

        abort_unless($isOwner || $isAdmin, 403, 'Forbidden');

        $disk = $document->disk ?? 'private';
        $path = $document->path;
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'Document not found');
        }

        // Audit every access — admin and owner alike.
        $action = $isAdmin ? 'kyc.document.admin_downloaded' : 'kyc.document.owner_downloaded';
        $this->auditLogs->record(
            $user->id,
            $action,
            KycDocument::class,
            $document->id,
            [
                'submission_id' => $document->submission_id,
                'owner_id' => $document->user_id,
                'doc_type' => $document->doc_type,
                'is_admin' => $isAdmin,
            ]
        );

        $mime = $document->mime_type ?? Storage::disk($disk)->mimeType($path) ?? 'application/octet-stream';
        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
        $disposition = $inline ? 'inline' : 'attachment';
        $safeName = $this->sanitizeFilename($document->original_name);

        $stream = Storage::disk($disk)->readStream($path);

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function sanitizeFilename(?string $name): string
    {
        $name = $name ?: 'document';
        $name = basename($name);
        $name = Str::of($name)->replace(['"', "'"], '')->toString();

        return $name ?: 'document';
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
