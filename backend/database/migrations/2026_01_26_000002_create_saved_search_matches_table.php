<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_search_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained('saved_searches')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->timestamp('matched_at');
            $table->timestamps();

            $table->unique(['saved_search_id', 'listing_id']);
            $table->index('saved_search_id');
            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_search_matches');
    }
};
