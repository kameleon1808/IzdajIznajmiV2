<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('geocoded_at')->nullable()->after('lng');
            $table->index(['lat', 'lng'], 'listings_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_lat_lng_index');
            $table->dropColumn('geocoded_at');
        });
    }
};
