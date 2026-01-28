<?php

namespace App\Jobs;

use App\Models\ChatAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class ProcessChatAttachmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $attachmentId)
    {
    }

    public function handle(): void
    {
        $attachment = ChatAttachment::find($this->attachmentId);
        if (!$attachment || $attachment->kind !== 'image') {
            return;
        }

        $diskName = $attachment->disk ?: 'private';
        $disk = Storage::disk($diskName);
        if (!$disk->exists($attachment->path_original)) {
            return;
        }

        $width = (int) config('chat.attachments.thumb_width', 480);
        $quality = (int) config('chat.attachments.webp_quality', 80);

        try {
            $manager = new ImageManager(['driver' => 'gd']);
            $img = $manager->read($disk->path($attachment->path_original));
            if ($width > 0 && $img->width() > $width) {
                $img = $img->scale($width, null);
            }
            $encoded = $img->toWebp($quality);
            $uuid = Str::uuid()->toString();
            $thumbPath = dirname($attachment->path_original, 1).'/thumbs/'.$uuid.'.webp';
            $disk->put($thumbPath, (string) $encoded);
            $attachment->update(['path_thumb' => $thumbPath]);
        } catch (Throwable $e) {
            Log::warning('chat_attachment_processing_failed', [
                'attachment_id' => $attachment->id,
                'conversation_id' => $attachment->conversation_id,
                'message_id' => $attachment->message_id,
                'error' => $e->getMessage(),
            ]);
            // Fallback to original so UI doesn't stay in processing state.
            $attachment->update(['path_thumb' => $attachment->path_original]);
            return;
        }
    }
}
