<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ChatAttachmentController extends Controller
{
    public function show(Request $request, ChatAttachment $attachment): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($attachment->conversation && $attachment->conversation->isParticipant($user), 403, 'Forbidden');

        $disk = Storage::disk($attachment->disk ?: 'private');
        abort_unless($disk->exists($attachment->path_original), 404, 'Attachment not found');

        $path = $disk->path($attachment->path_original);
        $filename = $attachment->original_name ?: basename($attachment->path_original);
        $mime = $attachment->mime_type ?: 'application/octet-stream';

        if ($attachment->kind === 'document') {
            return response()->download($path, $filename, ['Content-Type' => $mime]);
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function thumb(Request $request, ChatAttachment $attachment): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($attachment->conversation && $attachment->conversation->isParticipant($user), 403, 'Forbidden');

        abort_unless($attachment->path_thumb, 404, 'Thumbnail not found');

        $disk = Storage::disk($attachment->disk ?: 'private');
        abort_unless($disk->exists($attachment->path_thumb), 404, 'Thumbnail not found');

        $path = $disk->path($attachment->path_thumb);

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            'Content-Disposition' => 'inline; filename="thumb-'.basename($attachment->path_thumb).'"',
        ]);
    }
}
