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

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `users` MODIFY `gender` ENUM('muski','zenski','ne_zelim_da_kazem') NULL");
        DB::statement("ALTER TABLE `users` MODIFY `employment_status` ENUM('zaposlen','nezaposlen','student','penzioner') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')
            ->whereNotNull('gender')
            ->whereNotIn('gender', ['muski', 'zenski'])
            ->update(['gender' => null]);

        DB::table('users')
            ->whereNotNull('employment_status')
            ->whereNotIn('employment_status', ['zaposlen', 'nezaposlen', 'student'])
            ->update(['employment_status' => null]);

        DB::statement("ALTER TABLE `users` MODIFY `gender` ENUM('muski','zenski') NULL");
        DB::statement("ALTER TABLE `users` MODIFY `employment_status` ENUM('zaposlen','nezaposlen','student') NULL");
    }
};
