<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rating_reports', function (Blueprint $table) {
            $table->foreignId('listing_rating_id')
                ->nullable()
                ->after('rating_id')
                ->constrained('listing_ratings')
                ->cascadeOnDelete();
            $table->unique(['listing_rating_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::table('rating_reports', function (Blueprint $table) {
            $table->dropUnique(['listing_rating_id', 'reporter_id']);
            $table->dropForeign(['listing_rating_id']);
            $table->dropColumn('listing_rating_id');
        });
    }
};
