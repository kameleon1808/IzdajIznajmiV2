<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add AV scan status to kyc_documents
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->string('av_status')->default('pending')->after('path');
            $table->timestamp('av_scanned_at')->nullable()->after('av_status');
        });

        // Add purge_after to kyc_submissions
        Schema::table('kyc_submissions', function (Blueprint $table) {
            $table->timestamp('purge_after')->nullable()->after('reviewer_note');
            $table->index('purge_after');
        });

        // Extend kyc_submissions.status enum to include 'quarantined'
        // PostgreSQL stores Laravel enums as VARCHAR with a CHECK constraint
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE kyc_submissions DROP CONSTRAINT IF EXISTS kyc_submissions_status_check");
            DB::statement("ALTER TABLE kyc_submissions ADD CONSTRAINT kyc_submissions_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn', 'quarantined'))");
        }
        // SQLite treats enum as TEXT — no constraint to update
        // MySQL: handled below
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE kyc_submissions MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'withdrawn', 'quarantined') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropColumn(['av_status', 'av_scanned_at']);
        });

        Schema::table('kyc_submissions', function (Blueprint $table) {
            $table->dropIndex(['purge_after']);
            $table->dropColumn('purge_after');
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE kyc_submissions DROP CONSTRAINT IF EXISTS kyc_submissions_status_check");
            DB::statement("ALTER TABLE kyc_submissions ADD CONSTRAINT kyc_submissions_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn'))");
        }
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE kyc_submissions MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending'");
        }
    }
};
