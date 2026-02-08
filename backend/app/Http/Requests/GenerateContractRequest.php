<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input('start_date') ?? $this->input('startDate'),
        ]);
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
