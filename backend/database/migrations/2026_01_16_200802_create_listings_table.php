<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('address')->nullable();
            $table->string('city');
            $table->string('country');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('price_per_month');
            $table->decimal('rating', 3, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('beds');
            $table->unsignedTinyInteger('baths');
            $table->enum('category', ['villa', 'hotel', 'apartment']);
            $table->boolean('instant_book')->default(false);
            $table->timestamps();
            $table->index(['owner_id', 'category', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
