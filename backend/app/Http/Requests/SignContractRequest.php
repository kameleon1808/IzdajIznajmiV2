<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'typed_name' => $this->input('typed_name') ?? $this->input('typedName'),
        ]);
    }

    public function rules(): array
    {
        return [
            'typed_name' => ['required', 'string', 'max:120'],
            'consent' => ['required', 'accepted'],
        ];
    }
}
