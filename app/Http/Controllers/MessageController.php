<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function updates(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(Gate::allows('view', $conversation), 404);

        $data = $request->validate([
            'after' => ['required', 'integer', 'min:0'],
        ]);

        $messages = $conversation->messages()
            ->where('id', '>', $data['after'])
            ->with('sender:id,name,avatar_path')
            ->oldest('id')
            ->get();

        $unreadMessageIds = $messages
            ->filter(fn (Message $message): bool => $message->sender_id !== $request->user()->id && $message->read_at === null)
            ->pluck('id');

        if ($unreadMessageIds->isNotEmpty()) {
            $conversation->messages()
                ->whereIn('id', $unreadMessageIds)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $lastSeenMessageId = $conversation->messages()
            ->where('sender_id', $request->user()->id)
            ->whereNotNull('read_at')
            ->max('id');

        return response()->json([
            'data' => $messages->map(fn (Message $message): array => $this->messagePayload($message)),
            'last_seen_message_id' => $lastSeenMessageId,
        ])->header('Cache-Control', 'no-store');
    }

    public function presence(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(Gate::allows('view', $conversation), 404);

        return response()->json([
            'data' => [
                'presence_status' => $conversation->otherParticipantFor($request->user())->messengerPresenceStatus(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        abort_unless(Gate::allows('sendMessage', $conversation), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->messagePayload($message->load('sender:id,name,avatar_path')),
            ], 201);
        }

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', 'Your message was sent.');
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
            'display_time' => $message->created_at->format('M j, g:i a'),
            'read_at' => $message->read_at?->toIso8601String(),
        ];
    }
}
