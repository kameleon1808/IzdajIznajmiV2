<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use App\Services\ListingStatusService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        $city = $this->faker->city();
        $country = $this->faker->country();
        $address = $this->faker->streetAddress();

        return [
            'owner_id' => User::factory()->state(['role' => 'landlord']),
            'title' => $this->faker->sentence(3),
            'address' => $address,
            'address_key' => strtolower(trim("{$address} {$city} {$country}")),
            'city' => $city,
            'country' => $country,
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'geocoded_at' => now(),
            'price_per_night' => $this->faker->numberBetween(30, 300),
            'rating' => 0,
            'reviews_count' => 0,
            'cover_image' => null,
            'description' => $this->faker->sentence(8),
            'beds' => $this->faker->numberBetween(1, 5),
            'baths' => $this->faker->numberBetween(1, 3),
            'rooms' => $this->faker->numberBetween(1, 6),
            'area' => $this->faker->numberBetween(20, 200),
            'floor' => $this->faker->numberBetween(0, 12),
            'not_last_floor' => $this->faker->boolean(70),
            'not_ground_floor' => $this->faker->boolean(85),
            'heating' => $this->faker->randomElement(Listing::HEATING_VALUES),
            'condition' => $this->faker->randomElement(Listing::CONDITION_VALUES),
            'furnishing' => $this->faker->randomElement(Listing::FURNISHING_VALUES),
            'category' => $this->faker->randomElement(Listing::CATEGORY_VALUES),
            'instant_book' => false,
            'status' => ListingStatusService::STATUS_ACTIVE,
            'published_at' => now(),
            'archived_at' => null,
            'expired_at' => null,
            'location_source' => 'geocoded',
            'location_accuracy_m' => null,
            'location_overridden_at' => null,
        ];
    }
}
