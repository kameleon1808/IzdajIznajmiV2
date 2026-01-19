<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('address_key')->nullable()->after('address');
            $table->unsignedTinyInteger('rooms')->nullable()->after('baths');
            $table->unsignedInteger('area')->nullable()->after('rooms');
            $table->string('status_new', 20)->default('draft')->after('category');
            $table->timestamp('expired_at')->nullable()->after('archived_at');
            $table->index('address_key');
        });

        DB::table('listings')
            ->orderBy('id')
            ->chunkById(200, function ($listings): void {
                foreach ($listings as $listing) {
                    $statusMap = [
                        'published' => 'active',
                        'archived' => 'archived',
                        'draft' => 'draft',
                    ];
                    $normalizedStatus = $statusMap[$listing->status] ?? 'draft';
                    $addressKey = $this->normalizeAddressKey($listing->address, $listing->city, $listing->country);

                    DB::table('listings')
                        ->where('id', $listing->id)
                        ->update([
                            'status_new' => $normalizedStatus,
                            'rooms' => $listing->rooms ?? $listing->beds ?? null,
                            'address_key' => $addressKey,
                        ]);
                }
            });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_status_index');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->index(['address_key', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['address_key', 'status']);
            $table->dropIndex(['address_key']);
            $table->dropIndex(['status']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['address_key', 'rooms', 'area', 'expired_at']);
        });
    }

    private function normalizeAddressKey(?string $address, ?string $city, ?string $country): ?string
    {
        $parts = array_filter([$address, $city, $country], fn ($part) => filled($part));
        if (empty($parts)) {
            return null;
        }

        $normalized = Str::of(implode(' ', $parts))
            ->lower()
            ->replaceMatches('/[\\p{P}\\p{S}]+/u', ' ')
            ->replaceMatches('/\\s+/', ' ')
            ->trim()
            ->toString();

        return $normalized ?: null;
    }
};
