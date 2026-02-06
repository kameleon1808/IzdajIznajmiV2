<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_scores');
    }
};
