@extends('layouts.app')

@section('title', 'Your messages — Pasabuy')

@section('content')
    <section class="messages-shell">
        <div class="messages-heading">
            <div>
                <p class="eyebrow eyebrow-small">Student to student</p>
                <h1>Your messages</h1>
                <p class="auth-intro">Private conversations about the school finds you’re buying or renting.</p>
            </div>
        </div>

        @if ($conversations->isNotEmpty())
            <div class="conversation-list">
                @foreach ($conversations as $conversation)
                    @php($otherParticipant = $conversation->otherParticipantFor(auth()->user()))
                    <a class="conversation-row" href="{{ route('conversations.show', $conversation) }}">
                        <span class="conversation-avatar">
                            @if ($otherParticipant->avatar_url)
                                <img class="conversation-avatar-image" src="{{ $otherParticipant->avatar_url }}" alt="" aria-hidden="true">
                            @else
                                {{ collect(explode(' ', $otherParticipant->name))->map(fn (string $part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('') }}
                            @endif
                        </span>
                        <span class="conversation-copy">
                            <span class="conversation-topline">
                                <span class="conversation-person">
                                    <strong>{{ $otherParticipant->name }}</strong>
                                    <span class="presence-status" data-presence-status="{{ $otherParticipant->messengerPresenceStatus() }}" data-presence-url="{{ route('conversations.presence', $conversation) }}">
                                        <span class="presence-dot" aria-hidden="true"></span>
                                        <span data-presence-label>{{ ucfirst($otherParticipant->messengerPresenceStatus()) }}</span>
                                    </span>
                                </span>
                                <span>{{ $conversation->latestMessage?->created_at?->diffForHumans() }}</span>
                            </span>
                            <span class="conversation-listing">{{ $conversation->listing->title }}</span>
                            <span class="conversation-preview">{{ \Illuminate\Support\Str::limit($conversation->latestMessage?->body ?? 'Open the conversation', 110) }}</span>
                        </span>
                        @if ($conversation->unread_messages_count > 0)
                            <span class="unread-badge" aria-label="{{ $conversation->unread_messages_count }} unread messages">{{ $conversation->unread_messages_count }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="pagination-wrap">{{ $conversations->links() }}</div>
        @else
            <div class="empty-state conversation-empty">
                <span class="empty-icon" aria-hidden="true">✉</span>
                <h2>No conversations yet.</h2>
                <p>Open any school find and message its seller to talk through the details privately.</p>
                <a class="button button-navy" href="{{ route('marketplace.index') }}#discover">Browse school finds</a>
            </div>
        @endif
    </section>
@endsection
