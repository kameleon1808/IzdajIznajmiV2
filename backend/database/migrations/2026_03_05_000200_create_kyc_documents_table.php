<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('kyc_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('doc_type', ['id_front', 'id_back', 'selfie', 'proof_of_address']);
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk')->default('private');
            $table->string('path');
            $table->timestamps();
            $table->index('submission_id');
            $table->index('user_id');
            $table->index('doc_type');
            $table->unique(['submission_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
