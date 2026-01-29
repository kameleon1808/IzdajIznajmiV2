<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
