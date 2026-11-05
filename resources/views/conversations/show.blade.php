@extends('layouts.app')

@section('title', 'Chat about '.$conversation->listing->title.' — PASABUY')

@section('content')
    <section class="conversation-shell">
        <a class="back-link" href="{{ route('conversations.index') }}">← Back to your messages</a>

        <header class="conversation-header">
            <span class="conversation-avatar">{{ collect(explode(' ', $otherParticipant->name))->map(fn (string $part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('') }}</span>
            <div class="conversation-header-copy">
                <p class="eyebrow eyebrow-small">Private conversation</p>
                <h1>{{ $otherParticipant->name }}</h1>
                <a href="{{ route('listings.show', $conversation->listing) }}">{{ $conversation->listing->title }}</a>
            </div>
            <span class="conversation-type">{{ $conversation->listing->type === 'rent' ? 'For rent' : 'For sale' }}</span>
        </header>

        <div class="message-thread" aria-label="Conversation messages">
            @if ($messages->isNotEmpty())
                @foreach ($messages as $message)
                    <article class="message-bubble {{ $message->sender_id === auth()->id() ? 'message-own' : '' }}">
                        <span class="message-sender">{{ $message->sender->name }}</span>
                        <p>{{ $message->body }}</p>
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, g:i a') }}</time>
                    </article>
                @endforeach
                <div class="pagination-wrap">{{ $messages->links() }}</div>
            @else
                <div class="empty-state">
                    <h2>Your private chat starts here.</h2>
                    <p>Say hello and work out the details directly with {{ $otherParticipant->name }}.</p>
                </div>
            @endif
        </div>

        <form class="message-compose" method="POST" action="{{ route('conversations.messages.store', $conversation) }}">
            @csrf
            <label class="form-label" for="message-body">Write a message</label>
            @if ($errors->has('body'))
                <p class="message-error" role="alert">{{ $errors->first('body') }}</p>
            @endif
            <div class="message-compose-row">
                <textarea class="form-control" id="message-body" name="body" maxlength="2000" placeholder="Ask a question, make an offer, or arrange a time to meet..." required>{{ old('body') }}</textarea>
                <button class="button button-navy" type="submit">Send message <span aria-hidden="true">→</span></button>
            </div>
            <p class="message-hint">Keep your conversation respectful and meet in a public campus spot.</p>
        </form>
    </section>
@endsection
