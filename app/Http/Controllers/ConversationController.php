<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('conversations.index', [
            'conversations' => Conversation::query()
                ->where(function (Builder $query) use ($user): void {
                    $query->where('buyer_id', $user->id)
                        ->orWhere('seller_id', $user->id);
                })
                ->with(['listing', 'buyer', 'seller', 'latestMessage.sender'])
                ->withCount([
                    'messages as unread_messages_count' => fn (Builder $query): Builder => $query
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at'),
                ])
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless(Gate::allows('view', $conversation), 404);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load(['listing', 'buyer', 'seller']);

        return view('conversations.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()
                ->with('sender')
                ->oldest('created_at')
                ->oldest('id')
                ->paginate(50),
            'otherParticipant' => $conversation->otherParticipantFor($request->user()),
        ]);
    }

    public function store(Request $request, Listing $listing): RedirectResponse
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

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', 'Your message was sent.');
    }
}
