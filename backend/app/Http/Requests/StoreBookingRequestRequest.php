<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listingId' => ['required', 'exists:listings,id'],
            'landlordId' => ['required', 'exists:users,id'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
