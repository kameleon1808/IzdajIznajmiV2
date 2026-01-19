<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'pricePerNight' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'in:villa,hotel,apartment'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'min:30'],
            'beds' => ['required', 'integer', 'min:1', 'max:50'],
            'baths' => ['required', 'integer', 'min:1', 'max:50'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'area' => ['sometimes', 'integer', 'min:10', 'max:100000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'coverIndex' => ['sometimes', 'integer', 'min:0'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'instantBook' => ['sometimes', 'boolean'],
        ];
    }
}
