<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index('category');
            $table->index('price_per_month');
            $table->index('city');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['price_per_month']);
            $table->dropIndex(['city']);
            $table->dropIndex(['owner_id']);
        });
    }
};
