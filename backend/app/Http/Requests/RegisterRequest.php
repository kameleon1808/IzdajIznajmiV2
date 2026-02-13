<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:muski,zenski'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'employment_status' => ['nullable', 'string', 'in:zaposlen,nezaposlen,student'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'string', 'in:seeker,landlord,admin,tenant'],
            'address_book' => ['nullable', 'array'],
        ];
    }
}
