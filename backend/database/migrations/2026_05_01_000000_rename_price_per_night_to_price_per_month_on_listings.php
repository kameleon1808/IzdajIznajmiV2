<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        if (Schema::hasColumn('listings', 'price_per_night') && ! Schema::hasColumn('listings', 'price_per_month')) {
            if (Schema::hasIndex('listings', 'idx_listings_status_city_price_rooms_expired')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->dropIndex('idx_listings_status_city_price_rooms_expired');
                });
            }

            if (Schema::hasIndex('listings', 'listings_price_per_night_index')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->dropIndex('listings_price_per_night_index');
                });
            }

            Schema::table('listings', function (Blueprint $table) {
                $table->renameColumn('price_per_night', 'price_per_month');
            });

            if (! Schema::hasIndex('listings', 'listings_price_per_month_index')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->index('price_per_month');
                });
            }

            if (! Schema::hasIndex('listings', 'idx_listings_status_city_price_rooms_expired')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->index(
                        ['status', 'city', 'price_per_month', 'rooms', 'expired_at'],
                        'idx_listings_status_city_price_rooms_expired'
                    );
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        if (Schema::hasColumn('listings', 'price_per_month') && ! Schema::hasColumn('listings', 'price_per_night')) {
            if (Schema::hasIndex('listings', 'idx_listings_status_city_price_rooms_expired')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->dropIndex('idx_listings_status_city_price_rooms_expired');
                });
            }

            if (Schema::hasIndex('listings', 'listings_price_per_month_index')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->dropIndex('listings_price_per_month_index');
                });
            }

            Schema::table('listings', function (Blueprint $table) {
                $table->renameColumn('price_per_month', 'price_per_night');
            });

            if (! Schema::hasIndex('listings', 'listings_price_per_night_index')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->index('price_per_night');
                });
            }

            if (! Schema::hasIndex('listings', 'idx_listings_status_city_price_rooms_expired')) {
                Schema::table('listings', function (Blueprint $table) {
                    $table->index(
                        ['status', 'city', 'price_per_night', 'rooms', 'expired_at'],
                        'idx_listings_status_city_price_rooms_expired'
                    );
                });
            }
        }
    }
};
