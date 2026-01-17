<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->after('category');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->timestamp('archived_at')->nullable()->after('published_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'published_at', 'archived_at']);
        });
    }
};
