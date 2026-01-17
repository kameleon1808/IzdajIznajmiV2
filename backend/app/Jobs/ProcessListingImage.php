<?php

namespace App\Jobs;

use App\Models\ListingImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class ProcessListingImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $listingImageId, public string $originalPath)
    {
    }

    public function handle(): void
    {
        $image = ListingImage::find($this->listingImageId);
        if (!$image) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($this->originalPath)) {
            $image->update([
                'processing_status' => 'failed',
                'processing_error' => 'Original not found',
            ]);
            return;
        }

        $optimize = filter_var(env('IMAGE_OPTIMIZE', true), FILTER_VALIDATE_BOOL);
        $maxWidth = (int) env('IMAGE_MAX_WIDTH', 1600);
        $quality = (int) env('IMAGE_WEBP_QUALITY', 80);

        try {
            if ($optimize) {
                $manager = new ImageManager(['driver' => 'gd']);
                $img = $manager->read($disk->path($this->originalPath));
                if ($maxWidth > 0 && $img->width() > $maxWidth) {
                    $img = $img->scale($maxWidth, null);
                }
                $encoded = $img->toWebp($quality);
                $uuid = Str::uuid()->toString();
                $finalPath = dirname($this->originalPath, 1).'/'.$uuid.'.webp';
                $disk->put($finalPath, (string) $encoded);
                $image->update([
                    'url' => $disk->url($finalPath),
                    'processing_status' => 'done',
                    'processing_error' => null,
                ]);
                if ($image->is_cover) {
                    $image->listing()->update(['cover_image' => $disk->url($finalPath)]);
                }
                // optionally delete original
                $disk->delete($this->originalPath);
                return;
            }
        } catch (Throwable $e) {
            // fall back to original
            $image->update([
                'url' => $disk->url($this->originalPath),
                'processing_status' => 'done',
                'processing_error' => $e->getMessage(),
            ]);
            return;
        }

        $image->update([
            'url' => $disk->url($this->originalPath),
            'processing_status' => 'done',
        ]);
    }
}
