<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('landlord_verification_status', 'verification_status');
            $table->renameColumn('landlord_verified_at', 'verified_at');
            $table->renameColumn('landlord_verification_notes', 'verification_notes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('verification_status', 'landlord_verification_status');
            $table->renameColumn('verified_at', 'landlord_verified_at');
            $table->renameColumn('verification_notes', 'landlord_verification_notes');
        });
    }
};
