<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreViewingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('scheduledAt') && ! $this->has('scheduled_at')) {
            $this->merge(['scheduled_at' => $this->input('scheduledAt')]);
        }
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
