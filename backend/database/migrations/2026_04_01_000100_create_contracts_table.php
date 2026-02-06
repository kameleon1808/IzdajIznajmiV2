<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('rental_transactions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('template_key');
            $table->string('contract_hash');
            $table->string('pdf_path');
            $table->json('rendered_payload');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['transaction_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
