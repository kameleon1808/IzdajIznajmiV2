<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index(
                ['status', 'city', 'price_per_night', 'rooms', 'expired_at'],
                'idx_listings_status_city_price_rooms_expired'
            );
            $table->index(['status', 'expired_at'], 'idx_listings_status_expired_at');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'idx_notifications_user_read_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at'], 'idx_messages_conversation_created_at');
        });

        Schema::table('rental_transactions', function (Blueprint $table) {
            $table->index(['listing_id', 'status'], 'idx_rental_transactions_listing_status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('idx_listings_status_city_price_rooms_expired');
            $table->dropIndex('idx_listings_status_expired_at');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_read_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_conversation_created_at');
        });

        Schema::table('rental_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_rental_transactions_listing_status');
        });
    }
};
