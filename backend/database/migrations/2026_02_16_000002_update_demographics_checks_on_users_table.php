<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_gender_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_employment_status_check');

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IS NULL OR gender IN ('muski','zenski','ne_zelim_da_kazem'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_employment_status_check CHECK (employment_status IS NULL OR employment_status IN ('zaposlen','nezaposlen','student','penzioner'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_gender_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_employment_status_check');

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IS NULL OR gender IN ('muski','zenski'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_employment_status_check CHECK (employment_status IS NULL OR employment_status IN ('zaposlen','nezaposlen','student'))");
    }
};
