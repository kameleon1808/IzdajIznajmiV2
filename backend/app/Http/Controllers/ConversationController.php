<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatSpamGuardService;
use App\Services\ListingStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private ChatSpamGuardService $spamGuard)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $conversations = Conversation::with([
            'tenant:id,name',
            'landlord:id,name',
            'listing.images',
            'messages' => fn ($query) => $query->latest()->limit(1),
        ])
            ->where(fn ($query) => $query->where('tenant_id', $user->id)->orWhere('landlord_id', $user->id))
            ->orderByDesc('updated_at')
            ->get();

        $conversations->each(fn ($conversation) => $conversation->unread_count = $conversation->unreadCountFor($user));

        return response()->json(ConversationResource::collection($conversations));
    }

    public function conversationForListing(Request $request, Listing $listing): JsonResponse
    {
        $user = $this->requireSeeker($request, $listing);
        $conversation = $this->findOrCreateListingConversation($listing, $user);
        $conversation->loadMissing(['tenant:id,name', 'landlord:id,name', 'listing.images', 'messages' => fn ($query) => $query->latest()->limit(1)]);
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return response()->json(new ConversationResource($conversation));
    }

    public function conversationForApplication(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Only landlords can start this conversation');
        abort_if($application->landlord_id !== $user->id && !$this->userHasRole($user, 'admin'), 403, 'Forbidden');

        $listing = $application->listing()->with(['images'])->first();
        $this->assertListingActive($listing);

        $conversation = Conversation::firstOrCreate(
            [
                'listing_id' => $application->listing_id,
                'tenant_id' => $application->seeker_id,
                'landlord_id' => $application->landlord_id,
            ],
            []
        );

        $conversation->loadMissing(['tenant:id,name', 'landlord:id,name', 'listing.images', 'messages' => fn ($query) => $query->latest()->limit(1)]);
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return response()->json(new ConversationResource($conversation));
    }

    public function messagesForListing(Request $request, Listing $listing): JsonResponse
    {
        $user = $this->requireSeeker($request, $listing);
        $conversation = $this->findOrCreateListingConversation($listing, $user);

        $messages = $conversation->messages()->latest()->limit(200)->get()->sortBy('created_at')->values();
        $conversation->markReadFor($user);

        return response()->json(MessageResource::collection($messages));
    }

    public function sendMessageForListing(SendMessageRequest $request, Listing $listing): JsonResponse
    {
        $user = $this->requireSeeker($request, $listing);
        $conversation = $this->findOrCreateListingConversation($listing, $user);
        $conversation->loadMissing('listing');
        $this->assertListingActive($conversation->listing);
        $this->spamGuard->assertCanSend($user, $conversation);

        $message = $this->storeMessage($conversation, $user, $request->validated()['message']);

        return response()->json(new MessageResource($message), 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $messages = $conversation->messages()->latest()->limit(200)->get()->sortBy('created_at')->values();
        $conversation->markReadFor($user);

        return response()->json(MessageResource::collection($messages));
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $conversation->loadMissing('listing');
        $this->assertListingActive($conversation->listing);
        $this->spamGuard->assertCanSend($user, $conversation);

        $message = $this->storeMessage($conversation, $user, $request->validated()['message']);

        return response()->json(new MessageResource($message), 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $conversation->markReadFor($user);

        return response()->json(['message' => 'Read']);
    }

    private function storeMessage(Conversation $conversation, User $sender, string $body): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        return $message->fresh();
    }

    private function participantOrAbort(Request $request, Conversation $conversation): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($conversation->isParticipant($user), 403, 'Forbidden');

        return $user;
    }

    private function findOrCreateListingConversation(Listing $listing, User $seeker, bool $createIfMissing = true): ?Conversation
    {
        $conversation = Conversation::where([
            'listing_id' => $listing->id,
            'tenant_id' => $seeker->id,
            'landlord_id' => $listing->owner_id,
        ])->first();

        if ($conversation) {
            return $conversation;
        }

        if (!$createIfMissing) {
            return null;
        }

        $this->assertListingActive($listing);

        return Conversation::create([
            'listing_id' => $listing->id,
            'tenant_id' => $seeker->id,
            'landlord_id' => $listing->owner_id,
        ]);
    }

    private function assertListingActive(?Listing $listing): void
    {
        if (!$listing) {
            abort(422, 'Listing is not available for messaging');
        }

        if ($listing->status !== ListingStatusService::STATUS_ACTIVE) {
            abort(422, 'Listing is not active for messaging');
        }
    }

    private function requireSeeker(Request $request, ?Listing $listing = null): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['seeker', 'admin']), 403, 'Only seekers can start conversations from a listing');
        if ($listing && $listing->owner_id === $user->id) {
            abort(403, 'Cannot message your own listing');
        }

        return $user;
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
