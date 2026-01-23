<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('location_source', ['geocoded', 'manual'])->default('geocoded')->after('geocoded_at');
            $table->unsignedInteger('location_accuracy_m')->nullable()->after('location_source');
            $table->timestamp('location_overridden_at')->nullable()->after('location_accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['location_source', 'location_accuracy_m', 'location_overridden_at']);
        });
    }
};
