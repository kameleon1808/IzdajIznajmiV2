<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreKycSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = (int) config('kyc.max_file_size_kb', 10240);
        $allowed = implode(',', (array) config('kyc.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp', 'pdf']));

        return [
            'id_front' => ['required', 'file', "mimes:{$allowed}", "max:{$maxSize}"],
            'id_back' => ['nullable', 'file', "mimes:{$allowed}", "max:{$maxSize}"],
            'selfie' => ['required', 'file', "mimes:{$allowed}", "max:{$maxSize}"],
            'proof_of_address' => ['required', 'file', "mimes:{$allowed}", "max:{$maxSize}"],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $fields = ['id_front', 'id_back', 'selfie', 'proof_of_address'];
                foreach ($fields as $field) {
                    $file = $this->file($field);
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $detected = $this->detectMagicMime($file);
                    if ($detected === null) {
                        $validator->errors()->add($field, 'Could not determine file type. Please upload a valid image or PDF.');

                        continue;
                    }

                    $allowedMagic = (array) config('kyc.allowed_magic_mimes', [
                        'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
                    ]);

                    if (! in_array($detected, $allowedMagic, true)) {
                        $validator->errors()->add($field, "File type '{$detected}' is not permitted. Allowed: images (JPEG, PNG, WebP) and PDF.");
                    }
                }
            },
        ];
    }

    /**
     * Detect MIME type from the actual file bytes using finfo (magic bytes),
     * independent of client-supplied Content-Type or file extension.
     */
    private function detectMagicMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);

            return $mime ?: null;
        }

        // Fallback: mime_content_type (uses libmagic under the hood)
        if (function_exists('mime_content_type')) {
            return mime_content_type($path) ?: null;
        }

        return null;
    }
}
