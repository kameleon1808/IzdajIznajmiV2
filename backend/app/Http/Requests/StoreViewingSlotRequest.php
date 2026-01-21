<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreViewingSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'pattern' => ['sometimes', 'string', 'in:everyday,weekdays,weekends,custom,once'],
            'days_of_week' => ['sometimes', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'time_from' => ['sometimes', 'string'],
            'time_to' => ['sometimes', 'string'],
        ];
    }
}
