<?php

namespace App\Http\Requests;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->keepImages)) {
            $decoded = json_decode($this->keepImages, true);
            if (is_array($decoded)) {
                $this->merge(['keepImages' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'pricePerNight' => ['sometimes', 'integer', 'min:1'],
            'category' => ['sometimes', Rule::in(Listing::CATEGORY_VALUES)],
            'city' => ['sometimes', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'min:30'],
            'beds' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'baths' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'rooms' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'area' => ['sometimes', 'integer', 'min:10', 'max:100000'],
            'floor' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'notLastFloor' => ['sometimes', 'boolean'],
            'notGroundFloor' => ['sometimes', 'boolean'],
            'heating' => ['sometimes', Rule::in(Listing::HEATING_VALUES)],
            'condition' => ['sometimes', Rule::in(Listing::CONDITION_VALUES)],
            'furnishing' => ['sometimes', Rule::in(Listing::FURNISHING_VALUES)],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'keepImages' => ['sometimes', 'array'],
            'keepImages.*.url' => ['required_with:keepImages', 'string', 'max:2048'],
            'keepImages.*.sortOrder' => ['nullable', 'integer', 'min:0'],
            'keepImages.*.isCover' => ['sometimes', 'boolean'],
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
