<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFiles = (int) config('chat.attachments.max_files', 5);
        $maxKb = (int) config('chat.attachments.max_kb', 10240);

        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'max:'.$maxKb, 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $body = trim((string) ($this->input('body') ?? $this->input('message')));
            $hasAttachments = $this->hasFile('attachments');
            if ($body === '' && ! $hasAttachments) {
                $validator->errors()->add('body', 'Message body is required when no attachments are provided.');
            }
        });
    }
}
