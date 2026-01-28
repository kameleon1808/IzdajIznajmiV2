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
            [
                'address' => 'Kralja Milana 22',
                'lat' => 44.806631,
                'lng' => 20.469613,
            ],
            [
                'address' => 'Terazije 26',
                'lat' => 44.813413,
                'lng' => 20.461605,
            ],
            [
                'address' => 'Takovska 11',
                'lat' => 44.815388,
                'lng' => 20.469648,
            ],
            [
                'address' => 'Dečanska 8',
                'lat' => 44.8119,
                'lng' => 20.46552,
            ],
            [
                'address' => 'Makedonska 28',
                'lat' => 44.81294,
                'lng' => 20.46882,
            ],
            [
                'address' => 'Francuska 14',
                'lat' => 44.81852,
                'lng' => 20.4641,
            ],
            [
                'address' => 'Strahinjića Bana 28',
                'lat' => 44.82565,
                'lng' => 20.45782,
            ],
            [
                'address' => 'Skadarska 32',
                'lat' => 44.81761,
                'lng' => 20.46631,
            ],
            [
                'address' => 'Hilandarska 7',
                'lat' => 44.8122,
                'lng' => 20.46952,
            ],
            [
                'address' => 'Palmotićeva 29',
                'lat' => 44.81132,
                'lng' => 20.47276,
            ],
            [
                'address' => 'Bulevar despota Stefana 28',
                'lat' => 44.8209,
                'lng' => 20.4759,
            ],
            [
                'address' => 'Dobračina 7',
                'lat' => 44.8192,
                'lng' => 20.4619,
            ],
            [
                'address' => 'Knez Miletina 10',
                'lat' => 44.8197,
                'lng' => 20.473,
            ],
            [
                'address' => 'Gundulićev venac 23',
                'lat' => 44.8202,
                'lng' => 20.4713,
            ],
            [
                'address' => 'Kralja Petra 53',
                'lat' => 44.8178,
                'lng' => 20.4569,
            ],
            [
                'address' => 'Venizelosova 24',
                'lat' => 44.8215,
                'lng' => 20.4718,
            ],
            [
                'address' => 'Dunavska 22',
                'lat' => 44.8249,
                'lng' => 20.4722,
            ],
            [
                'address' => 'Ruzveltova 34',
                'lat' => 44.8051,
                'lng' => 20.4874,
            ],
            [
                'address' => 'Kraljice Marije 47',
                'lat' => 44.8059,
                'lng' => 20.4896,
            ],
            [
                'address' => 'Vojvode Stepe 101',
                'lat' => 44.7721,
                'lng' => 20.4755,
            ],
            [
                'address' => 'Bulevar oslobođenja 46',
                'lat' => 44.7926,
                'lng' => 20.4691,
            ],
            [
                'address' => 'Južni bulevar 95',
                'lat' => 44.7921,
                'lng' => 20.4768,
            ],
            [
                'address' => 'Njegoševa 21',
                'lat' => 44.8069,
                'lng' => 20.4734,
            ],
            [
                'address' => 'Krunska 64',
                'lat' => 44.8079,
                'lng' => 20.4806,
            ],
            [
                'address' => 'Svetog Save 14',
                'lat' => 44.8034,
                'lng' => 20.4682,
            ],
            [
                'address' => 'Makenzijeva 58',
                'lat' => 44.8026,
                'lng' => 20.4794,
            ],
            [
                'address' => 'Mileševska 9',
                'lat' => 44.8039,
                'lng' => 20.4812,
            ],
            [
                'address' => 'Ustanička 190',
                'lat' => 44.7792,
                'lng' => 20.5041,
            ],
            [
                'address' => 'Mirijevski bulevar 78',
                'lat' => 44.811,
                'lng' => 20.5158,
            ],
            [
                'address' => 'Vojislava Ilića 141',
                'lat' => 44.7933,
                'lng' => 20.5015,
            ],
        ];

        foreach ($beogradListings as $i => $data) {
            $ownerId = $landlordIds[$i % max(count($landlordIds), 1)] ?? null;
            if (!$ownerId) {
                break;
            }

            $images = collect($imagePool)->shuffle()->take(3)->values();
            $addressKey = $addressGuard->normalizeAddressKey($data['address'], 'Beograd', 'Srbija');
            $status = 'active';
            $rooms = $data['rooms'] ?? $faker->numberBetween(1, 3);
            $area = $data['area'] ?? $faker->numberBetween(35, 120);
            $price = $data['price'] ?? $faker->numberBetween(60, 180);
            $title = $data['title'] ?? ('Belgrade Stay - ' . $data['address']);

            $listing = Listing::create([
                'owner_id' => $ownerId,
                'title' => $title,
                'address' => $data['address'],
                'address_key' => $addressKey,
                'city' => 'Beograd',
                'country' => 'Srbija',
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'geocoded_at' => now(),
                'price_per_night' => $price,
                'rating' => $faker->randomFloat(1, 4.2, 5),
                'reviews_count' => $faker->numberBetween(10, 150),
                'cover_image' => $images->first(),
                'description' => $faker->sentences(3, true),
                'beds' => max(2, $rooms),
                'baths' => 1,
                'rooms' => $rooms,
                'area' => $area,
                'category' => $data['category'] ?? $categories[array_rand($categories)],
                'instant_book' => true,
                'status' => $status,
                'published_at' => now()->subDays(rand(1, 10)),
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
