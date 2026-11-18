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
            ->with('sender:id,name')
            ->oldest('id')
            ->get();

        $conversation->messages()
            ->whereIn('id', $messages->pluck('id'))
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => $messages->map(fn (Message $message): array => $this->messagePayload($message)),
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
                'data' => $this->messagePayload($message->load('sender:id,name')),
            ], 201);
        }

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', 'Your message was sent.');
    }

    /**
     * @return array<string, int|string>
     */
    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->name,
            'body' => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
            'display_time' => $message->created_at->format('M j, g:i a'),
        ];
    }
}
