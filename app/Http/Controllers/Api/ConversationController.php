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
use Illuminate\Support\Carbon;
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
            ->map(fn (Conversation $conversation): array => $this->conversationData($conversation, $user));

        return response()->json(['data' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless(Gate::allows('view', $conversation), 404);

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load(['listing', 'buyer', 'seller', 'latestMessage']);
        $messages = $conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message): array => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'body' => $message->body,
                'created_at' => $message->created_at,
            ]);

        return response()->json([
            'data' => [
                ...$this->conversationData($conversation, $user),
                'messages' => $messages,
            ],
        ]);
    }

    public function start(Request $request, Listing $listing): JsonResponse
    {
        Gate::authorize('message', $listing);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = DB::transaction(function () use ($listing, $request, $data): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                [
                    'listing_id' => $listing->id,
                    'buyer_id' => $request->user()->id,
                ],
                ['seller_id' => $listing->user_id],
            );

            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $data['body'],
            ]);

            return $conversation;
        });

        $conversation->load(['listing', 'buyer', 'seller', 'latestMessage']);

        return response()->json([
            'data' => $this->conversationData($conversation, $request->user()),
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
            'data' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'body' => $message->body,
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    /**
     * @return array{
     *     id: int,
     *     listing: array{id: int, title: string, slug: string, type: string, price: string, rent_period: ?string, image_url: ?string},
     *     other_participant: array{id: int, name: string, campus: ?string, avatar_url: ?string},
     *     latest_message: array{body: string, created_at: Carbon}|null,
     *     unread_messages_count: int
     * }
     */
    private function conversationData(Conversation $conversation, User $user): array
    {
        $otherParticipant = $conversation->otherParticipantFor($user);
        $latestMessage = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'listing' => [
                'id' => $conversation->listing->id,
                'title' => $conversation->listing->title,
                'slug' => $conversation->listing->slug,
                'type' => $conversation->listing->type,
                'price' => $conversation->listing->price,
                'rent_period' => $conversation->listing->rent_period,
                'image_url' => $conversation->listing->image_url,
            ],
            'other_participant' => [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->name,
                'campus' => $otherParticipant->campus,
                'avatar_url' => $otherParticipant->avatar_url,
            ],
            'latest_message' => $latestMessage ? [
                'body' => $latestMessage->body,
                'created_at' => $latestMessage->created_at,
            ] : null,
            'unread_messages_count' => $conversation->unread_messages_count ?? 0,
        ];
    }
}
