<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('landlord_verification_status', ['none', 'pending', 'approved', 'rejected'])
                ->default('none')
                ->after('is_suspicious');
            $table->timestamp('landlord_verified_at')->nullable()->after('landlord_verification_status');
            $table->text('landlord_verification_notes')->nullable()->after('landlord_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['landlord_verification_status', 'landlord_verified_at', 'landlord_verification_notes']);
        });
    }
};
