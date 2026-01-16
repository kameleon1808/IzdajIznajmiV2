<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $conversations = Conversation::with(['tenant', 'landlord', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->where(fn ($query) => $query->where('tenant_id', $user->id)->orWhere('landlord_id', $user->id))
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(ConversationResource::collection($conversations));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless(in_array($user->id, [$conversation->tenant_id, $conversation->landlord_id]), 403, 'Forbidden');

        $messages = $conversation->messages()->latest()->limit(50)->get()->sortBy('created_at')->values();

        return response()->json(MessageResource::collection($messages));
    }
}
