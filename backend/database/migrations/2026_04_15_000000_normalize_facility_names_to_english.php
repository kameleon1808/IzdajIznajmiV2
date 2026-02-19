<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->remapFacilities($this->forwardMap());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->remapFacilities($this->reverseMap());
    }

    /**
     * @param  array<string, string>  $map
     */
    private function remapFacilities(array $map): void
    {
        $targets = array_values(array_unique(array_values($map)));
        foreach ($targets as $targetName) {
            $this->ensureFacilityExists($targetName);
        }

        foreach ($map as $sourceName => $targetName) {
            if ($sourceName === $targetName) {
                continue;
            }

            $this->mergeByName($sourceName, $targetName);
        }
    }

    private function ensureFacilityExists(string $name): int
    {
        $existing = DB::table('facilities')
            ->where('name', $name)
            ->orderBy('id')
            ->first(['id']);

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('facilities')->insertGetId([
            'name' => $name,
            'icon' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mergeByName(string $sourceName, string $targetName): void
    {
        $targetId = $this->ensureFacilityExists($targetName);

        $sourceIds = DB::table('facilities')
            ->where('name', $sourceName)
            ->where('id', '!=', $targetId)
            ->pluck('id');

        foreach ($sourceIds as $sourceId) {
            $listingIds = DB::table('facility_listing')
                ->where('facility_id', $sourceId)
                ->pluck('listing_id');

            foreach ($listingIds as $listingId) {
                $exists = DB::table('facility_listing')
                    ->where('listing_id', $listingId)
                    ->where('facility_id', $targetId)
                    ->exists();

                if (! $exists) {
                    DB::table('facility_listing')->insert([
                        'listing_id' => $listingId,
                        'facility_id' => $targetId,
                    ]);
                }
            }

            DB::table('facility_listing')->where('facility_id', $sourceId)->delete();
            DB::table('facilities')->where('id', $sourceId)->delete();
        }
    }

    /**
     * @return array<string, string>
     */
    private function forwardMap(): array
    {
        return [
            'Basement' => 'Basement',
            'Podrum' => 'Basement',
            'Garage' => 'Garage',
            'Garaza' => 'Garage',
            'Garaža' => 'Garage',
            'Parking' => 'Garage',
            'Terrace' => 'Terrace',
            'Terasa' => 'Terrace',
            'Yard' => 'Yard',
            'Dvoriste' => 'Yard',
            'Dvorište' => 'Yard',
            'Internet' => 'Internet',
            'Cable TV' => 'Cable TV',
            'Cable' => 'Cable TV',
            'Kablovska' => 'Cable TV',
            'Phone' => 'Phone',
            'Telefon' => 'Phone',
            'Air conditioning' => 'Air conditioning',
            'Air-conditioning' => 'Air conditioning',
            'AC' => 'Air conditioning',
            'Klima' => 'Air conditioning',
            'Elevator' => 'Elevator',
            'Lift' => 'Elevator',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function reverseMap(): array
    {
        return [
            'Basement' => 'Podrum',
            'Podrum' => 'Podrum',
            'Garage' => 'Garaza',
            'Garaza' => 'Garaza',
            'Garaža' => 'Garaza',
            'Parking' => 'Garaza',
            'Terrace' => 'Terasa',
            'Terasa' => 'Terasa',
            'Yard' => 'Dvoriste',
            'Dvoriste' => 'Dvoriste',
            'Dvorište' => 'Dvoriste',
            'Internet' => 'Internet',
            'Cable TV' => 'Kablovska',
            'Cable' => 'Kablovska',
            'Kablovska' => 'Kablovska',
            'Phone' => 'Telefon',
            'Telefon' => 'Telefon',
            'Air conditioning' => 'Klima',
            'Air-conditioning' => 'Klima',
            'AC' => 'Klima',
            'Klima' => 'Klima',
            'Elevator' => 'Lift',
            'Lift' => 'Lift',
        ];
    }
};

