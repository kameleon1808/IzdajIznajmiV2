<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\ProcessChatAttachmentJob;
use App\Models\Application;
use App\Models\ChatAttachment;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatSpamGuardService;
use App\Services\FraudSignalService;
use App\Services\ListingStatusService;
use App\Services\StructuredLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function __construct(
        private ChatSpamGuardService $spamGuard,
        private StructuredLogger $log,
        private FraudSignalService $fraudSignals
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $conversations = Conversation::with([
            'tenant:id,name',
            'landlord:id,name',
            'listing.images',
            'messages' => fn ($query) => $query->latest()->limit(1)->with('attachments'),
        ])
            ->where(fn ($query) => $query->where('tenant_id', $user->id)->orWhere('landlord_id', $user->id))
            ->orderByDesc('updated_at')
            ->get();

        $conversations->each(fn ($conversation) => $conversation->unread_count = $conversation->unreadCountFor($user));

        return response()->json(ConversationResource::collection($conversations));
    }

    public function conversationForListing(Request $request, Listing $listing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $participants = $this->resolveListingConversationParticipants($request, $listing, $user);
        $conversation = $this->findOrCreateListingConversation($listing, $participants['seeker'], $participants['landlord']);
        $conversation->loadMissing(['tenant:id,name', 'landlord:id,name', 'listing.images', 'messages' => fn ($query) => $query->latest()->limit(1)->with('attachments')]);
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return response()->json(new ConversationResource($conversation));
    }

    public function conversationForApplication(Request $request, Application $application): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($this->userHasRole($user, ['landlord', 'admin']), 403, 'Only landlords can start this conversation');
        abort_if($application->landlord_id !== $user->id && ! $this->userHasRole($user, 'admin'), 403, 'Forbidden');

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

        $conversation->loadMissing(['tenant:id,name', 'landlord:id,name', 'listing.images', 'messages' => fn ($query) => $query->latest()->limit(1)->with('attachments')]);
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return response()->json(new ConversationResource($conversation));
    }

    public function messagesForListing(Request $request, Listing $listing): JsonResponse
    {
        $user = $this->requireSeeker($request, $listing);
        $conversation = $this->findOrCreateListingConversation($listing, $user);

        $messages = $conversation->messages()
            ->with('attachments')
            ->latest()
            ->limit(200)
            ->get()
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();
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

        $body = trim((string) ($request->input('body') ?? $request->input('message')));
        $message = $this->storeMessage($conversation, $user, $body, $request->file('attachments', []));
        $this->recordRapidMessages($user);

        return response()->json(new MessageResource($message), 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'after' => ['nullable', 'date'],
        ]);

        $sinceId = (int) ($validated['since_id'] ?? 0);
        $after = isset($validated['after']) ? Carbon::parse($validated['after']) : null;

        $summaryQuery = $conversation->messages();
        $this->applyIncrementalMessagesFilter($summaryQuery, $sinceId, $after);
        $summary = (clone $summaryQuery)
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(MAX(id), 0) as max_id')
            ->first();
        $count = (int) ($summary?->aggregate_count ?? 0);
        $maxId = (int) ($summary?->max_id ?? 0);
        $etag = $this->messagesCollectionEtag($conversation->id, $sinceId, $after, $count, $maxId);

        $notModified = response()
            ->json([])
            ->setEtag($etag)
            ->header('Cache-Control', 'private, must-revalidate');
        if ($notModified->isNotModified($request)) {
            return $notModified;
        }

        $messagesQuery = $conversation->messages()->with('attachments');
        $this->applyIncrementalMessagesFilter($messagesQuery, $sinceId, $after);
        $messages = $messagesQuery
            ->latest()
            ->limit(200)
            ->get()
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();

        $hasIncrementalFilter = $sinceId > 0 || $after !== null;
        $hasIncomingMessages = $messages->contains(fn (Message $message) => (int) $message->sender_id !== (int) $user->id);

        if (! $hasIncrementalFilter || $hasIncomingMessages) {
            $conversation->markReadFor($user);
        }

        return response()
            ->json(MessageResource::collection($messages))
            ->setEtag($etag)
            ->header('Cache-Control', 'private, must-revalidate');
    }

    private function applyIncrementalMessagesFilter(Builder|HasMany $query, int $sinceId, ?Carbon $after): void
    {
        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);

            return;
        }

        if ($after) {
            $query->where('created_at', '>', $after);
        }
    }

    private function messagesCollectionEtag(
        int $conversationId,
        int $sinceId,
        ?Carbon $after,
        int $count,
        int $maxId
    ): string {
        return sha1(implode('|', [
            'messages',
            $conversationId,
            $sinceId,
            $after?->toISOString() ?? '',
            $count,
            $maxId,
        ]));
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $conversation->loadMissing([
            'tenant:id,name',
            'landlord:id,name',
            'listing.images',
            'messages' => fn ($query) => $query->latest()->limit(1)->with('attachments'),
        ]);
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return response()->json(new ConversationResource($conversation));
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $conversation->loadMissing('listing');
        $this->assertListingActive($conversation->listing);
        $this->spamGuard->assertCanSend($user, $conversation);

        $body = trim((string) ($request->input('body') ?? $request->input('message')));
        $message = $this->storeMessage($conversation, $user, $body, $request->file('attachments', []));
        $this->recordRapidMessages($user);

        return response()->json(new MessageResource($message), 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participantOrAbort($request, $conversation);
        $conversation->markReadFor($user);

        return response()->json(['message' => 'Read']);
    }

    private function storeMessage(Conversation $conversation, User $sender, ?string $body, array $attachments = []): Message
    {
        $body = trim((string) $body);
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        $message = $message->fresh();

        if (! empty($attachments)) {
            foreach ($attachments as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $stored = $this->storeAttachment($conversation, $message, $sender, $file);
                if ($stored && $stored->kind === 'image') {
                    ProcessChatAttachmentJob::dispatch($stored->id);
                }
            }
        }

        event(new MessageCreated($message));

        $this->log->info('chat_message_sent', [
            'conversation_id' => $conversation->id,
            'listing_id' => $conversation->listing_id,
            'user_id' => $sender->id,
            'message_length' => mb_strlen($body),
        ]);

        return $message->loadMissing('attachments');
    }

    private function recordRapidMessages(User $user): void
    {
        $settings = config('security.fraud.signals.rapid_messages', []);
        $threshold = (int) ($settings['threshold'] ?? 12);
        $windowMinutes = (int) ($settings['window_minutes'] ?? 5);
        $weight = (int) ($settings['weight'] ?? 5);

        $count = Message::where('sender_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($count >= $threshold) {
            $this->fraudSignals->recordSignal(
                $user,
                'rapid_messages',
                $weight,
                ['count' => $count],
                $windowMinutes
            );
        }
    }

    private function storeAttachment(Conversation $conversation, Message $message, User $sender, UploadedFile $file): ?ChatAttachment
    {
        $diskName = 'private';
        $disk = Storage::disk($diskName);
        $basePath = 'chat/'.$conversation->id;
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $disk->putFileAs($basePath, $file, $filename);

        if (! $path) {
            return null;
        }

        $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
        $kind = str_starts_with($mime, 'image/') ? 'image' : 'document';

        return ChatAttachment::create([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'uploader_id' => $sender->id,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'disk' => $diskName,
            'path_original' => $path,
        ]);
    }

    private function participantOrAbort(Request $request, Conversation $conversation): User
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');
        abort_unless($conversation->isParticipant($user), 403, 'Forbidden');

        return $user;
    }

    private function findOrCreateListingConversation(Listing $listing, User $seeker, ?User $landlord = null, bool $createIfMissing = true): ?Conversation
    {
        $landlord = $landlord ?: $listing->owner;
        $conversation = Conversation::where([
            'listing_id' => $listing->id,
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord?->id ?? $listing->owner_id,
        ])->first();

        if ($conversation) {
            return $conversation;
        }

        if (! $createIfMissing) {
            return null;
        }

        $this->assertListingActive($listing);

        return Conversation::create([
            'listing_id' => $listing->id,
            'tenant_id' => $seeker->id,
            'landlord_id' => $landlord?->id ?? $listing->owner_id,
        ]);
    }

    private function resolveListingConversationParticipants(Request $request, Listing $listing, User $user): array
    {
        $isAdmin = $this->userHasRole($user, 'admin');
        $isListingOwner = (int) $listing->owner_id === (int) $user->id;

        if ($this->userHasRole($user, ['seeker', 'admin']) && ! $isListingOwner) {
            return ['seeker' => $user, 'landlord' => $listing->owner ?? null];
        }

        if ($this->userHasRole($user, ['landlord', 'admin']) && ($isListingOwner || $isAdmin)) {
            $seekerId = $request->input('seeker_id');
            abort_unless($seekerId, 422, 'seeker_id is required');
            $seeker = User::find($seekerId);
            abort_if(! $seeker, 404, 'Seeker not found');

            return ['seeker' => $seeker, 'landlord' => $listing->owner ?? $user];
        }

        abort(403, 'Forbidden');
    }

    private function assertListingActive(?Listing $listing): void
    {
        if (! $listing) {
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
