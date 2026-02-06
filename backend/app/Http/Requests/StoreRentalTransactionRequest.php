<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRentalTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'listing_id' => $this->input('listing_id') ?? $this->input('listingId'),
            'seeker_id' => $this->input('seeker_id') ?? $this->input('seekerId'),
            'deposit_amount' => $this->input('deposit_amount') ?? $this->input('depositAmount'),
            'rent_amount' => $this->input('rent_amount') ?? $this->input('rentAmount'),
        ]);
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'seeker_id' => ['required', 'integer', 'exists:users,id'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
