<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('buyer_id', $user->id)
                    ->orWhere('seller_id', $user->id);
            })
            ->with(['listing', 'buyer', 'seller', 'latestMessage'])
            ->withCount([
                'messages as unread_messages_count' => fn (Builder $query): Builder => $query
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ])
            ->latest('updated_at')
            ->get()
            ->map(fn (Conversation $conversation): array => $this->conversationPayload($conversation, $user))
            ->values();

        return response()->json(['data' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(Gate::allows('view', $conversation), 404);

        $user = $request->user();
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load(['listing', 'buyer', 'seller', 'latestMessage']);
        $conversation->loadCount([
            'messages as unread_messages_count' => fn (Builder $query): Builder => $query
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at'),
        ]);
        $messages = $conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->with('sender:id,name,avatar_path')
            ->get(['id', 'sender_id', 'body', 'created_at', 'read_at'])
            ->reverse()
            ->values()
            ->map(fn (Message $message): array => $this->messagePayload($message));

        return response()->json([
            'data' => [
                ...$this->conversationPayload($conversation, $user),
                'messages' => $messages,
            ],
        ]);
    }

    public function storeForListing(Request $request, Listing $listing): JsonResponse
    {
        Gate::authorize('message', $listing);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $user = $request->user();

        $conversation = DB::transaction(function () use ($listing, $user, $data): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                [
                    'listing_id' => $listing->id,
                    'buyer_id' => $user->id,
                ],
                ['seller_id' => $listing->user_id],
            );

            $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $data['body'],
            ]);

            return $conversation;
        });

        $this->loadConversationPayloadRelations($conversation, $user);

        return response()->json([
            'data' => $this->conversationPayload($conversation, $user),
        ], 201);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(Gate::allows('sendMessage', $conversation), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json([
            'data' => $this->messagePayload($message->load('sender:id,name,avatar_path')),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationPayload(Conversation $conversation, User $user): array
    {
        $otherParticipant = $conversation->otherParticipantFor($user);
        $latestMessage = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'listing' => $conversation->listing,
            'other_participant' => [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->name,
                'campus' => $otherParticipant->campus,
                'avatar_url' => $otherParticipant->avatar_url,
                'presence_status' => $otherParticipant->messengerPresenceStatus(),
            ],
            'latest_message' => $latestMessage ? [
                'body' => $latestMessage->body,
                'created_at' => $latestMessage->created_at,
            ] : null,
            'unread_messages_count' => (int) $conversation->unread_messages_count,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->name,
            'sender_avatar_url' => $message->sender->avatar_url,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
        ];
    }

    private function loadConversationPayloadRelations(Conversation $conversation, User $user): void
    {
        $conversation->load(['listing', 'buyer', 'seller', 'latestMessage']);
        $conversation->loadCount([
            'messages as unread_messages_count' => fn (Builder $query): Builder => $query
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at'),
        ]);
    }
}
