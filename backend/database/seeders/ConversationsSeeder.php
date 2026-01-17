<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationsSeeder extends Seeder
{
    public function run(): void
    {
        Conversation::truncate();
        Message::truncate();

        $tenant = User::where('role', 'seeker')->first();
        $landlord = User::where('role', 'landlord')->first();

        if (!$tenant || !$landlord) {
            return;
        }

        $conversation = Conversation::create([
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'listing_id' => null,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $landlord->id,
            'body' => 'Welcome to our place! Let us know your arrival time.',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $tenant->id,
            'body' => 'Thanks! We plan to arrive at 4PM.',
        ]);
    }
}
