<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'pricePerNight' => ['sometimes', 'integer', 'min:1'],
            'category' => ['sometimes', 'in:villa,hotel,apartment'],
            'city' => ['sometimes', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'min:30'],
            'beds' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'baths' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'keepImageUrls' => ['sometimes', 'array'],
            'keepImageUrls.*' => ['string', 'max:2048'],
            'removeImageUrls' => ['sometimes', 'array'],
            'removeImageUrls.*' => ['string', 'max:2048'],
            'facilities' => ['sometimes', 'array'],
            'facilities.*' => ['string'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'instantBook' => ['sometimes', 'boolean'],
        ];
    }
}
