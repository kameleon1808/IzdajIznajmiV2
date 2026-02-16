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
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('full_name');
            $table->enum('gender', ['muski', 'zenski', 'ne_zelim_da_kazem'])->nullable()->after('date_of_birth');
            $table->string('residential_address')->nullable()->after('gender');
            $table->enum('employment_status', ['zaposlen', 'nezaposlen', 'student', 'penzioner'])->nullable()->after('residential_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'gender',
                'residential_address',
                'employment_status',
            ]);
        });
    }
};
