@extends('layouts.app')

@section('title', 'Fetch requests — Pasabuy')

@section('content')
    <section class="fetch-shell">
        <header class="fetch-heading">
            <div>
                <p class="eyebrow">Campus request board</p>
                <h1>Live fetch requests</h1>
                <p>See what students need delivered around campus.</p>
            </div>
            <a class="button button-navy fetch-create-button" href="{{ route('fetch.requests.create') }}">
                <span aria-hidden="true">＋</span> Post a fetch request
            </a>
        </header>

        <section
            class="fetch-feed"
            aria-label="Live fetch requests"
            data-fetch-feed
            data-updates-url="{{ route('fetch.requests.updates') }}"
            data-last-id="{{ $requests->max('id') ?? 0 }}"
        >
            <p class="fetch-feed-status" data-fetch-status role="status" aria-live="polite">Live updates every 15 seconds.</p>

            <div class="fetch-request-list" data-fetch-requests>
                @forelse ($requests as $fetchRequest)
                    <article
                        class="listing-card fetch-request-card"
                        data-fetch-request-id="{{ $fetchRequest->id }}"
                        data-expires-at="{{ $fetchRequest->effectiveExpiresAt()->toIso8601String() }}"
                    >
                        <div class="fetch-request-heading">
                            <div>
                                <h3>{{ $fetchRequest->item_name }}</h3>
                                <p class="fetch-requester">Posted by {{ $fetchRequest->user->name }}</p>
                            </div>
                            <span class="fetch-budget">
                                @if ($fetchRequest->budget !== null)
                                    Up to ₱{{ number_format((float) $fetchRequest->budget, $fetchRequest->budget == (int) $fetchRequest->budget ? 0 : 2) }}
                                @else
                                    Budget flexible
                                @endif
                            </span>
                        </div>
                        <p class="fetch-request-details">{{ $fetchRequest->details }}</p>
                        <div class="fetch-request-meta">
                            <span>Deliver to <strong>{{ $fetchRequest->delivery_location }}</strong></span>
                            <time class="fetch-expiry" datetime="{{ $fetchRequest->effectiveExpiresAt()->toIso8601String() }}">Expires {{ $fetchRequest->effectiveExpiresAt()->format('M j, g:i A') }}</time>
                        </div>
                    </article>
                @empty
                    <div class="empty-state fetch-empty-state" data-fetch-empty>
                        <h2>No live requests at the moment.</h2>
                        <p>Check back soon or post what you need.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </section>
@endsection
