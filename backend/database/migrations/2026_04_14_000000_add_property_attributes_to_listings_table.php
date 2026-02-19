<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor')->nullable()->after('area');
            $table->boolean('not_last_floor')->default(false)->after('floor');
            $table->boolean('not_ground_floor')->default(false)->after('not_last_floor');
            $table->string('heating', 30)->nullable()->after('not_ground_floor');
            $table->string('condition', 30)->nullable()->after('heating');
            $table->string('furnishing', 30)->nullable()->after('condition');
            $table->string('category_new', 20)->default('apartment')->after('category');
        });

        DB::table('listings')->update([
            'category_new' => DB::raw("CASE WHEN category IN ('villa', 'hotel', 'apartment', 'house') THEN category ELSE 'apartment' END"),
        ]);

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'category', 'city']);
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->renameColumn('category_new', 'category');
            $table->index(['owner_id', 'category', 'city']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('category_legacy', ['villa', 'hotel', 'apartment'])->default('apartment')->after('category');
        });

        DB::table('listings')->update([
            'category_legacy' => DB::raw("CASE WHEN category = 'house' THEN 'villa' WHEN category IN ('villa', 'hotel', 'apartment') THEN category ELSE 'apartment' END"),
        ]);

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'category', 'city']);
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->renameColumn('category_legacy', 'category');
            $table->index(['owner_id', 'category', 'city']);
            $table->index('category');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['floor', 'not_last_floor', 'not_ground_floor', 'heating', 'condition', 'furnishing']);
        });
    }
};
