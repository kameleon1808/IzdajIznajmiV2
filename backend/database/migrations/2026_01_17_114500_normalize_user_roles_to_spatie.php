<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add a temporary column without the old enum check constraint, migrate data, then swap.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_new')->default('seeker')->after('role');
        });

        DB::table('users')->update([
            'role_new' => DB::raw("CASE role WHEN 'tenant' THEN 'seeker' WHEN 'landlord' THEN 'landlord' WHEN 'admin' THEN 'admin' ELSE role END"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_new', 'role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_old')->default('tenant')->after('role');
        });

        DB::table('users')->update([
            'role_old' => DB::raw("CASE role WHEN 'seeker' THEN 'tenant' ELSE role END"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_old', 'role');
        });
    }
};
