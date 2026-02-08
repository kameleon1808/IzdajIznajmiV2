<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_images', function (Blueprint $table) {
            $table->boolean('is_cover')->default(false)->after('sort_order');
            $table->index(['listing_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('listing_images', function (Blueprint $table) {
            $table->dropIndex(['listing_id', 'sort_order']);
            $table->dropColumn('is_cover');
        });
    }
};
