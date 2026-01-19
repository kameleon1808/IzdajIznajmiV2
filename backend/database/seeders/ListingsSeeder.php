<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingAddressGuardService;
use App\Services\ListingStatusService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingsSeeder extends Seeder
{
    public function run(): void
    {
        Listing::truncate();
        ListingImage::truncate();
        DB::table('facility_listing')->truncate();

        $landlordIds = User::where('role', 'landlord')->pluck('id')->all();
        $facilities = Facility::all();
        $addressGuard = app(ListingAddressGuardService::class);

        $imagePool = [
            'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1400&q=80',
            'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1400&q=80',
            'https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1400&q=80',
            'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80',
            'https://images.unsplash.com/photo-1501117716987-c8e1ecb210af?auto=format&fit=crop&w=1400&q=80',
        ];

        $categories = ['villa', 'hotel', 'apartment'];
        $faker = fake();
        $primaryLandlordId = $landlordIds[0] ?? null;

        if ($primaryLandlordId) {
            $featured = [
                [
                    'title' => 'Sunny Loft in City Center',
                    'address' => 'Ilica 12',
                    'city' => 'Zagreb',
                    'country' => 'Croatia',
                    'price' => 120,
                    'rooms' => 3,
                    'area' => 78,
                ],
                [
                    'title' => 'Modern Riverside Apartment',
                    'address' => 'Kneza Branimira 8',
                    'city' => 'Zagreb',
                    'country' => 'Croatia',
                    'price' => 95,
                    'rooms' => 2,
                    'area' => 62,
                ],
            ];

            foreach ($featured as $index => $data) {
                $images = collect($imagePool)->shuffle()->take(3)->values();
                $addressKey = $addressGuard->normalizeAddressKey($data['address'], $data['city'], $data['country']);

                $listing = Listing::create([
                    'owner_id' => $primaryLandlordId,
                    'title' => $data['title'],
                    'address' => $data['address'],
                    'address_key' => $addressKey,
                    'city' => $data['city'],
                    'country' => $data['country'],
                    'lat' => $faker->latitude(),
                    'lng' => $faker->longitude(),
                    'price_per_night' => $data['price'],
                    'rating' => $faker->randomFloat(1, 4.2, 5),
                    'reviews_count' => $faker->numberBetween(10, 150),
                    'cover_image' => $images->first(),
                    'description' => $faker->sentences(3, true),
                    'beds' => max(2, $data['rooms'] + 1),
                    'baths' => 1,
                    'rooms' => $data['rooms'],
                    'area' => $data['area'],
                    'category' => $categories[array_rand($categories)],
                    'instant_book' => true,
                    'status' => ListingStatusService::STATUS_ACTIVE,
                    'published_at' => now()->subDays(rand(1, 10)),
                ]);

                foreach ($images as $imgIndex => $url) {
                    ListingImage::create([
                        'listing_id' => $listing->id,
                        'url' => $url,
                        'sort_order' => $imgIndex,
                        'is_cover' => $imgIndex === 0,
                        'processing_status' => 'done',
                    ]);
                }

                $facilityIds = $facilities->shuffle()->take(rand(2, 6))->pluck('id')->all();
                $listing->facilities()->sync($facilityIds);
            }
        }

        for ($i = 0; $i < 18; $i++) {
            $ownerId = $landlordIds[$i % count($landlordIds)];
            $category = $categories[array_rand($categories)];
            $images = collect($imagePool)->shuffle()->take(3)->values();
            $statusPool = ['draft', 'active', 'archived', 'active', 'paused', 'rented'];
            $status = $statusPool[array_rand($statusPool)];
            $address = $faker->streetAddress();
            $city = $faker->city();
            $country = $faker->country();
            $addressKey = $addressGuard->normalizeAddressKey($address, $city, $country);

            $listing = Listing::create([
                'owner_id' => $ownerId,
                'title' => Str::headline($faker->words(3, true)),
                'address' => $address,
                'address_key' => $addressKey,
                'city' => $city,
                'country' => $country,
                'lat' => $faker->latitude(),
                'lng' => $faker->longitude(),
                'price_per_night' => $faker->numberBetween(80, 420),
                'rating' => $faker->randomFloat(1, 4.2, 5),
                'reviews_count' => $faker->numberBetween(20, 280),
                'cover_image' => $images->first(),
                'description' => $faker->sentences(3, true),
                'beds' => $faker->numberBetween(1, 5),
                'baths' => $faker->numberBetween(1, 4),
                'rooms' => $faker->numberBetween(1, 5),
                'area' => $faker->numberBetween(35, 220),
                'category' => $category,
                'instant_book' => $faker->boolean(60),
                'status' => $status,
                'published_at' => in_array($status, ['active', 'rented'], true) ? now()->subDays(rand(1, 30)) : null,
                'archived_at' => $status === 'archived' ? now()->subDays(rand(1, 30)) : null,
            ]);

            foreach ($images as $index => $url) {
                ListingImage::create([
                    'listing_id' => $listing->id,
                    'url' => $url,
                    'sort_order' => $index,
                    'is_cover' => $index === 0,
                    'processing_status' => 'done',
                ]);
            }

            $facilityIds = $facilities->shuffle()->take(rand(2, 6))->pluck('id')->all();
            $listing->facilities()->sync($facilityIds);
        }
    }
}
