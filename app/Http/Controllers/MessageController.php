<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(Gate::allows('sendMessage', $conversation), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', 'Your message was sent.');
    }
}
