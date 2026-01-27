<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->json('filters');
            $table->boolean('alerts_enabled')->default(true);
            $table->enum('frequency', ['instant', 'daily', 'weekly'])->default('instant');
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
