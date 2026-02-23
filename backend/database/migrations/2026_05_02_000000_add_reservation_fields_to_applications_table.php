<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'start_date')) {
                $table->date('start_date')->nullable()->after('message');
            }

            if (! Schema::hasColumn('applications', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }

            if (! Schema::hasColumn('applications', 'withdrawn_at')) {
                $table->timestamp('withdrawn_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'withdrawn_at')) {
                $table->dropColumn('withdrawn_at');
            }

            if (Schema::hasColumn('applications', 'end_date')) {
                $table->dropColumn('end_date');
            }

            if (Schema::hasColumn('applications', 'start_date')) {
                $table->dropColumn('start_date');
            }
        });
    }
};
