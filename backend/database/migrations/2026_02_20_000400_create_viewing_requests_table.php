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
        Schema::create('viewing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viewing_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seeker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->text('message')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();

            $table->index('listing_id');
            $table->index('viewing_slot_id');
            $table->index('seeker_id');
            $table->index('landlord_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viewing_requests');
    }
};
