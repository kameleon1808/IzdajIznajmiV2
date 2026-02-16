<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rating_reports') || ! Schema::hasColumn('rating_reports', 'rating_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteRatingReportsTable(true);

            return;
        }

        if ($driver === 'mysql') {
            Schema::table('rating_reports', function (Blueprint $table) {
                $table->dropForeign(['rating_id']);
            });

            DB::statement('ALTER TABLE `rating_reports` MODIFY `rating_id` BIGINT UNSIGNED NULL');

            Schema::table('rating_reports', function (Blueprint $table) {
                $table->foreign('rating_id')->references('id')->on('ratings')->cascadeOnDelete();
            });

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE rating_reports ALTER COLUMN rating_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rating_reports') || ! Schema::hasColumn('rating_reports', 'rating_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteRatingReportsTable(false);

            return;
        }

        DB::table('rating_reports')->whereNull('rating_id')->delete();

        if ($driver === 'mysql') {
            Schema::table('rating_reports', function (Blueprint $table) {
                $table->dropForeign(['rating_id']);
            });

            DB::statement('ALTER TABLE `rating_reports` MODIFY `rating_id` BIGINT UNSIGNED NOT NULL');

            Schema::table('rating_reports', function (Blueprint $table) {
                $table->foreign('rating_id')->references('id')->on('ratings')->cascadeOnDelete();
            });

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE rating_reports ALTER COLUMN rating_id SET NOT NULL');
        }
    }

    private function rebuildSqliteRatingReportsTable(bool $ratingIdNullable): void
    {
        $hasListingRatingId = Schema::hasColumn('rating_reports', 'listing_rating_id');
        $tempTable = 'rating_reports_tmp_nullable_fix';

        Schema::disableForeignKeyConstraints();
        try {
            Schema::create($tempTable, function (Blueprint $table) use ($hasListingRatingId, $ratingIdNullable) {
                $table->id();

                $ratingId = $table->foreignId('rating_id');
                if ($ratingIdNullable) {
                    $ratingId->nullable();
                }
                $ratingId->constrained('ratings')->cascadeOnDelete();

                if ($hasListingRatingId) {
                    $table->foreignId('listing_rating_id')->nullable()->constrained('listing_ratings')->cascadeOnDelete();
                }

                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason');
                $table->text('details')->nullable();
                $table->timestamps();

                $table->unique(['rating_id', 'reporter_id']);
                if ($hasListingRatingId) {
                    $table->unique(['listing_rating_id', 'reporter_id']);
                }
            });

            $columns = ['id', 'rating_id'];
            if ($hasListingRatingId) {
                $columns[] = 'listing_rating_id';
            }
            $columns[] = 'reporter_id';
            $columns[] = 'reason';
            $columns[] = 'details';
            $columns[] = 'created_at';
            $columns[] = 'updated_at';

            $source = DB::table('rating_reports')->select($columns);
            if (! $ratingIdNullable) {
                // Rollback path: nullable rows cannot fit into NOT NULL schema.
                $source->whereNotNull('rating_id');
            }

            DB::table($tempTable)->insertUsing($columns, $source);

            Schema::drop('rating_reports');
            Schema::rename($tempTable, 'rating_reports');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
