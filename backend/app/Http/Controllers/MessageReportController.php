<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageReportController extends Controller
{
    public function store(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless(in_array($user->id, [$message->sender_id, $message->conversation?->tenant_id, $message->conversation?->landlord_id], true), 403, 'Only participants can report messages');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $exists = Report::where([
            'reporter_id' => $user->id,
            'target_type' => Message::class,
            'target_id' => $message->id,
        ])->exists();

        if ($exists) {
            return response()->json(['message' => 'You already reported this message'], 422);
        }

        $report = Report::create([
            'reporter_id' => $user->id,
            'target_type' => Message::class,
            'target_id' => $message->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return response()->json($report, 201);
    }
}
