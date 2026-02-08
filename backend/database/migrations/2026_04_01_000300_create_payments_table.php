<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('rental_transactions')->cascadeOnDelete();
            $table->string('provider');
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status');
            $table->string('provider_intent_id')->nullable();
            $table->string('provider_checkout_session_id')->nullable();
            $table->string('receipt_url')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
