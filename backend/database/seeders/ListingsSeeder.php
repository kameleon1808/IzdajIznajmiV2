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
        $beogradListings = [
            [
                'title' => 'Sunny Loft Dorćol',
                'address' => 'Gospodar Jevremova 47',
                'lat' => 44.821987,
                'lng' => 20.463968,
                'price' => 120,
                'rooms' => 2,
                'area' => 78,
                'category' => 'apartment',
            ],
            [
                'title' => 'Knez Mihailova City View',
                'address' => 'Kneza Mihaila 42',
                'lat' => 44.816546,
                'lng' => 20.46013,
                'price' => 110,
                'rooms' => 1,
                'area' => 55,
                'category' => 'apartment',
            ],
            [
                'title' => 'Vračar Terrace Flat',
                'address' => 'Bulevar kralja Aleksandra 73',
                'lat' => 44.805829,
                'lng' => 20.478436,
                'price' => 95,
                'rooms' => 2,
                'area' => 62,
                'category' => 'apartment',
            ],
            [
                'title' => 'Savski Venac Studio',
                'address' => 'Nemanjina 4',
                'lat' => 44.807042,
                'lng' => 20.460075,
                'price' => 80,
                'rooms' => 1,
                'area' => 48,
                'category' => 'apartment',
            ],
            [
                'title' => 'Dorćol River Walk',
                'address' => 'Cara Dušana 73',
                'lat' => 44.825154,
                'lng' => 20.454356,
                'price' => 105,
                'rooms' => 2,
                'area' => 70,
                'category' => 'apartment',
            ],
            [
                'title' => 'New Belgrade Skyline',
                'address' => 'Bulevar Mihajla Pupina 117',
                'lat' => 44.822455,
                'lng' => 20.412301,
                'price' => 140,
                'rooms' => 3,
                'area' => 95,
                'category' => 'apartment',
            ],
            [
                'title' => 'A Blok Riverside',
                'address' => 'Jurija Gagarina 16',
                'lat' => 44.805029,
                'lng' => 20.382298,
                'price' => 130,
                'rooms' => 2,
                'area' => 85,
                'category' => 'apartment',
            ],
            [
                'title' => 'Banovo Brdo Calm',
                'address' => 'Požeška 83',
                'lat' => 44.770782,
                'lng' => 20.422057,
                'price' => 90,
                'rooms' => 2,
                'area' => 68,
                'category' => 'apartment',
            ],
            [
                'title' => 'Old Town Steps',
                'address' => 'Balkanska 18',
                'lat' => 44.810944,
                'lng' => 20.462866,
                'price' => 100,
                'rooms' => 1,
                'area' => 52,
                'category' => 'apartment',
            ],
            [
                'title' => 'Sava Port Flat',
                'address' => 'Savska 25',
                'lat' => 44.804775,
                'lng' => 20.457947,
                'price' => 115,
                'rooms' => 2,
                'area' => 74,
                'category' => 'apartment',
            ],
        ];

        foreach ($beogradListings as $i => $data) {
            $ownerId = $landlordIds[$i % max(count($landlordIds), 1)] ?? null;
            if (!$ownerId) {
                break;
            }

            $images = collect($imagePool)->shuffle()->take(3)->values();
            $addressKey = $addressGuard->normalizeAddressKey($data['address'], 'Beograd', 'Srbija');
            $statusPool = ['active', 'active', 'paused', 'draft'];
            $status = $statusPool[array_rand($statusPool)];

            $listing = Listing::create([
                'owner_id' => $ownerId,
                'title' => $data['title'],
                'address' => $data['address'],
                'address_key' => $addressKey,
                'city' => 'Beograd',
                'country' => 'Srbija',
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'geocoded_at' => now(),
                'price_per_night' => $data['price'],
                'rating' => $faker->randomFloat(1, 4.2, 5),
                'reviews_count' => $faker->numberBetween(10, 150),
                'cover_image' => $images->first(),
                'description' => $faker->sentences(3, true),
                'beds' => max(2, $data['rooms']),
                'baths' => 1,
                'rooms' => $data['rooms'],
                'area' => $data['area'],
                'category' => $data['category'] ?? $categories[array_rand($categories)],
                'instant_book' => true,
                'status' => $status,
                'published_at' => $status === 'active' ? now()->subDays(rand(1, 10)) : null,
                'archived_at' => $status === 'archived' ? now()->subDays(rand(1, 30)) : null,
                'location_source' => 'geocoded',
                'location_accuracy_m' => null,
                'location_overridden_at' => null,
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
