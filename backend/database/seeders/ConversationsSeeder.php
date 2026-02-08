<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ConversationsSeeder extends Seeder
{
    public function run(): void
    {
        Conversation::truncate();
        Message::truncate();

        $tenant = User::where('role', 'seeker')->first();
        $secondTenant = User::where('role', 'seeker')->skip(1)->first() ?? $tenant;
        $landlord = User::where('role', 'landlord')->first();
        $listingA = Listing::where('owner_id', $landlord?->id)->where('status', 'active')->first();
        $listingB = Listing::where('owner_id', $landlord?->id)->where('status', 'active')->skip(1)->first();
        $fallbackListing = Listing::where('status', 'active')->first();

        if (! $tenant || ! $landlord || ! $listingA || ! $fallbackListing) {
            return;
        }

        $now = Carbon::now();

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listingA->id,
            'tenant_last_read_at' => $now,
            'landlord_last_read_at' => $now,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $tenant->id,
            'body' => 'Hi! I just applied. Is the flat free next Thursday?',
            'created_at' => $now->copy()->subMinutes(12),
            'updated_at' => $now->copy()->subMinutes(12),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $tenant->id,
            'body' => 'Happy to adjust move-in dates if needed.',
            'created_at' => $now->copy()->subMinutes(10),
            'updated_at' => $now->copy()->subMinutes(10),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Yes, Thursday works. Would you like to schedule a viewing?',
            'created_at' => $now->copy()->subMinutes(8),
            'updated_at' => $now->copy()->subMinutes(8),
        ]);

        $conversationSpam = Conversation::create([
            'tenant_id' => $secondTenant->id,
            'landlord_id' => $landlord->id,
            'listing_id' => $listingB?->id ?? $fallbackListing->id,
            'tenant_last_read_at' => $now->copy()->subMinutes(20),
            'landlord_last_read_at' => null,
        ]);

        foreach ([18, 16, 14] as $minutes) {
            Message::create([
                'conversation_id' => $conversationSpam->id,
                'sender_id' => $secondTenant->id,
                'body' => 'Hello! I am very interested in this apartment.',
                'created_at' => $now->copy()->subMinutes($minutes),
                'updated_at' => $now->copy()->subMinutes($minutes),
            ]);
        }
    }
}
