@extends('layouts.app')

@section('title', 'Your messages — PASABUY')

@section('content')
    <section class="messages-shell">
        <div class="messages-heading">
            <div>
                <p class="eyebrow eyebrow-small">Student to student</p>
                <h1>Your messages</h1>
                <p class="auth-intro">Private conversations about the school finds you’re buying or renting.</p>
            </div>
            <a class="button button-navy" href="{{ route('marketplace.index') }}#discover">Find a school-day save <span aria-hidden="true">→</span></a>
        </div>

        @if ($conversations->isNotEmpty())
            <div class="conversation-list">
                @foreach ($conversations as $conversation)
                    @php($otherParticipant = $conversation->otherParticipantFor(auth()->user()))
                    <a class="conversation-row" href="{{ route('conversations.show', $conversation) }}">
                        <span class="conversation-avatar">{{ collect(explode(' ', $otherParticipant->name))->map(fn (string $part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('') }}</span>
                        <span class="conversation-copy">
                            <span class="conversation-topline">
                                <strong>{{ $otherParticipant->name }}</strong>
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
