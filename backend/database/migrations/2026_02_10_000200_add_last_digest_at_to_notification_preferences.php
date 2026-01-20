<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->timestamp('last_digest_daily_at')->nullable()->after('digest_enabled');
            $table->timestamp('last_digest_weekly_at')->nullable()->after('last_digest_daily_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['last_digest_daily_at', 'last_digest_weekly_at']);
        });
    }
};
