<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('avg_rating_30d', 3, 2)->nullable();
            $table->decimal('all_time_avg_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('median_response_time_minutes')->nullable();
            $table->unsignedInteger('completed_transactions_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_metrics');
    }
};
