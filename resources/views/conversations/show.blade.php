@extends('layouts.app')

@section('title', 'Chat about '.$conversation->listing->title.' — Pasabuy')

@section('content')
    <section class="conversation-shell">
        <a class="back-link" href="{{ route('conversations.index') }}">← Back to your messages</a>

        <header class="conversation-header">
            <span class="conversation-avatar">
                @if ($otherParticipant->avatar_url)
                    <img class="conversation-avatar-image" src="{{ $otherParticipant->avatar_url }}" alt="" aria-hidden="true">
                @else
                    {{ collect(explode(' ', $otherParticipant->name))->map(fn (string $part): string => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->take(2)->implode('') }}
                @endif
            </span>
            <div class="conversation-header-copy">
                <p class="eyebrow eyebrow-small">Private conversation</p>
                <h1>{{ $otherParticipant->name }}</h1>
                <span class="presence-status" data-presence-status="{{ $otherParticipant->messengerPresenceStatus() }}" data-presence-url="{{ route('conversations.presence', $conversation) }}">
                    <span class="presence-dot" aria-hidden="true"></span>
                    <span data-presence-label>{{ ucfirst($otherParticipant->messengerPresenceStatus()) }}</span>
                </span>
                <a href="{{ route('listings.show', $conversation->listing) }}">{{ $conversation->listing->title }}</a>
            </div>
            <span class="conversation-type">{{ $conversation->listing->type === 'rent' ? 'For rent' : 'For sale' }}</span>
        </header>

        <div class="message-thread" aria-label="Conversation messages"
            data-live-messages
            data-updates-url="{{ route('conversations.messages.updates', $conversation) }}"
            data-current-user="{{ auth()->id() }}"
            data-last-message-id="{{ $latestMessageId }}"
            data-last-seen-message-id="{{ $lastSeenMessageId }}"
            data-seen-avatar-url="{{ $otherParticipant->avatar_url }}"
            data-seen-initial="{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($otherParticipant->name, 0, 1)) }}">
            @if ($messages->isNotEmpty())
                @foreach ($messages as $message)
                    @php($messageIsOwn = $message->sender_id === auth()->id())
                    <div class="message-row {{ $messageIsOwn ? 'message-row-own' : '' }}">
                        <span class="message-avatar">
                            @if ($message->sender->avatar_url)
                                <img class="message-avatar-image" src="{{ $message->sender->avatar_url }}" alt="" aria-hidden="true">
                            @else
                                <span class="message-avatar-fallback" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($message->sender->name, 0, 1)) }}</span>
                            @endif
                        </span>
                        <div class="message-content">
                            <article class="message-bubble {{ $messageIsOwn ? 'message-own' : '' }}" data-message-id="{{ $message->id }}">
                                <span class="message-sender">{{ $message->sender->name }}</span>
                                <p>{{ $message->body }}</p>
                                <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, g:i a') }}</time>
                                @if ($messageIsOwn && $message->id === $lastSeenMessageId)
                                    <span class="message-seen" data-message-seen>
                                        <span class="message-seen-avatar">
                                            @if ($otherParticipant->avatar_url)
                                                <img src="{{ $otherParticipant->avatar_url }}" alt="" aria-hidden="true">
                                            @else
                                                <span aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($otherParticipant->name, 0, 1)) }}</span>
                                            @endif
                                        </span>
                                        <span>Seen</span>
                                    </span>
                                @endif
                            </article>
                        </div>
                    </div>
                @endforeach
                <div class="pagination-wrap">{{ $messages->links() }}</div>
            @else
                <div class="empty-state">
                    <h2>Your private chat starts here.</h2>
                    <p>Say hello and work out the details directly with {{ $otherParticipant->name }}.</p>
                </div>
            @endif
        </div>
        <p class="message-live-status" data-live-status role="status" aria-live="polite"></p>

        <form class="message-compose" method="POST" action="{{ route('conversations.messages.store', $conversation) }}" data-live-compose>
            @csrf
            <label class="form-label" for="message-body">Write a message</label>
            @if ($errors->has('body'))
                <p class="message-error" role="alert">{{ $errors->first('body') }}</p>
            @endif
            <p class="message-error" data-compose-error role="alert" hidden></p>
            <div class="message-compose-row">
                <textarea class="form-control" id="message-body" name="body" maxlength="2000" placeholder="Ask a question, make an offer, or arrange a time to meet..." required>{{ old('body') }}</textarea>
                <button class="button button-navy" type="submit">Send message <span aria-hidden="true">→</span></button>
            </div>
            <p class="message-compose-status" data-compose-status role="status" aria-live="polite"></p>
            <p class="message-hint">Keep your conversation respectful and meet in a public campus spot.</p>
        </form>
    </section>
@endsection
