<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('name');
            $table->string('phone')->nullable()->unique()->after('email');
            $table->json('address_book')->nullable()->after('phone');
            $table->boolean('email_verified')->default(false)->after('email_verified_at');
            $table->boolean('phone_verified')->default(false)->after('email_verified');
            $table->boolean('address_verified')->default(false)->after('phone_verified');
            $table->boolean('is_suspicious')->default(false)->after('address_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'phone',
                'address_book',
                'email_verified',
                'phone_verified',
                'address_verified',
                'is_suspicious',
            ]);
        });
    }
};
