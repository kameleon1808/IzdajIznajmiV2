<?php

namespace App\Http\Requests;

use Carbon\Carbon;
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
            'startDate' => ['required', 'date', 'after_or_equal:today'],
            'endDate' => ['required', 'date', 'after:startDate', $this->minimumMonthRule()],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    private function minimumMonthRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $startRaw = $this->input('startDate');
            if (! is_string($startRaw) || ! is_string($value) || $startRaw === '' || $value === '') {
                return;
            }

            try {
                $startDate = Carbon::parse($startRaw)->startOfDay();
                $endDate = Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $minimumEndDate = $startDate->copy()->addMonthNoOverflow();
            if ($endDate->lt($minimumEndDate)) {
                $fail('Reservation period must be at least one month.');
            }
        };
    }
}
