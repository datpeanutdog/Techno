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

        @if ($errors->any())
            <div class="form-errors" role="alert">
                <strong>The fetch request could not be updated.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($acceptedJobs->isNotEmpty())
            <section class="fetch-dashboard-section" aria-labelledby="fetch-jobs-heading">
                <header class="fetch-dashboard-heading">
                    <p class="eyebrow">Your dashboard</p>
                    <h2 id="fetch-jobs-heading">Jobs you accepted</h2>
                </header>
                <div class="fetch-request-list">
                    @foreach ($acceptedJobs as $fetchRequest)
                        <article
                            class="listing-card fetch-dashboard-card"
                            @if ($fetchRequest->status === \App\Models\FetchRequest::STATUS_ACCEPTED && now()->lessThan($fetchRequest->effectiveDeliveryDeadline()))
                                data-fetch-active-job
                                data-delivery-deadline="{{ $fetchRequest->effectiveDeliveryDeadline()->toIso8601String() }}"
                            @endif
                        >
                            <div class="fetch-request-heading">
                                <div>
                                    <h3>{{ $fetchRequest->item_name }}</h3>
                                    <p class="fetch-requester">Requested by {{ $fetchRequest->user->name }}</p>
                                </div>
                                <span class="fetch-job-status" data-fetch-job-status>
                                    @if ($fetchRequest->status === \App\Models\FetchRequest::STATUS_COMPLETED)
                                        Delivery confirmed
                                    @elseif ($fetchRequest->status === \App\Models\FetchRequest::STATUS_DELIVERED)
                                        Waiting for confirmation
                                    @elseif (now()->greaterThan($fetchRequest->effectiveDeliveryDeadline()))
                                        Deadline passed
                                    @else
                                        Currently doing this job
                                    @endif
                                </span>
                            </div>
                            <p class="fetch-request-details">{{ $fetchRequest->details }}</p>
                            <div class="fetch-request-meta">
                                <span>Deliver to <strong>{{ $fetchRequest->delivery_location }}</strong></span>
                                <time
                                    class="fetch-expiry"
                                    datetime="{{ $fetchRequest->effectiveDeliveryDeadline()->toIso8601String() }}"
                                >Deliver by {{ $fetchRequest->effectiveDeliveryDeadline()->format('M j, g:i A') }} UTC</time>
                            </div>
                            @if ($fetchRequest->status === \App\Models\FetchRequest::STATUS_ACCEPTED)
                                @if (now()->lessThanOrEqualTo($fetchRequest->effectiveDeliveryDeadline()))
                                    <form class="fetch-action-form" method="POST" action="{{ route('fetch.requests.delivered', $fetchRequest) }}">
                                        @csrf
                                        <button class="button button-navy" data-fetch-deliver-button type="submit">Mark as delivered</button>
                                    </form>
                                @else
                                    <p class="fetch-deadline-warning">The delivery deadline passed. This request cannot be marked delivered.</p>
                                @endif
                            @elseif ($fetchRequest->status === \App\Models\FetchRequest::STATUS_DELIVERED)
                                <p class="fetch-dashboard-note">Delivery reported {{ $fetchRequest->delivered_at?->format('M j, g:i A') }} UTC. Waiting for the requester to confirm.</p>
                            @elseif ($fetchRequest->status === \App\Models\FetchRequest::STATUS_COMPLETED)
                                <p class="fetch-dashboard-note">The requester confirmed delivery.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($postedRequests->isNotEmpty())
            <section class="fetch-dashboard-section" aria-labelledby="fetch-posted-heading">
                <header class="fetch-dashboard-heading">
                    <p class="eyebrow">Your dashboard</p>
                    <h2 id="fetch-posted-heading">Requests you posted</h2>
                </header>
                <div class="fetch-request-list">
                    @foreach ($postedRequests as $fetchRequest)
                        <article
                            class="listing-card fetch-dashboard-card"
                            @if ($fetchRequest->status === \App\Models\FetchRequest::STATUS_ACCEPTED && now()->lessThan($fetchRequest->effectiveDeliveryDeadline()))
                                data-fetch-active-job
                                data-delivery-deadline="{{ $fetchRequest->effectiveDeliveryDeadline()->toIso8601String() }}"
                            @endif
                        >
                            <div class="fetch-request-heading">
                                <div>
                                    <h3>{{ $fetchRequest->item_name }}</h3>
                                    <p class="fetch-requester">
                                        @if ($fetchRequest->acceptedBy)
                                            Accepted by {{ $fetchRequest->acceptedBy->name }}
                                        @else
                                            Accepted by another student
                                        @endif
                                    </p>
                                </div>
                                <span class="fetch-job-status" data-fetch-job-status>
                                    @if ($fetchRequest->status === \App\Models\FetchRequest::STATUS_COMPLETED)
                                        Delivery confirmed
                                    @elseif ($fetchRequest->status === \App\Models\FetchRequest::STATUS_DELIVERED)
                                        Confirm delivery
                                    @elseif (now()->greaterThan($fetchRequest->effectiveDeliveryDeadline()))
                                        Deadline passed
                                    @else
                                        Delivery Ongoing
                                    @endif
                                </span>
                            </div>
                            <div class="fetch-request-meta">
                                <span>Deliver to <strong>{{ $fetchRequest->delivery_location }}</strong></span>
                                <time
                                    class="fetch-expiry"
                                    datetime="{{ $fetchRequest->effectiveDeliveryDeadline()->toIso8601String() }}"
                                >Deliver by {{ $fetchRequest->effectiveDeliveryDeadline()->format('M j, g:i A') }} UTC</time>
                            </div>
                            @if ($fetchRequest->status === \App\Models\FetchRequest::STATUS_DELIVERED)
                                <p class="fetch-dashboard-note">Reported delivered {{ $fetchRequest->delivered_at?->format('M j, g:i A') }} UTC.</p>
                                <form class="fetch-action-form" method="POST" action="{{ route('fetch.requests.confirm', $fetchRequest) }}">
                                    @csrf
                                    <button class="button button-navy" type="submit">Confirm delivery</button>
                                </form>
                            @elseif ($fetchRequest->status === \App\Models\FetchRequest::STATUS_COMPLETED)
                                <p class="fetch-dashboard-note">Delivery was confirmed by you.</p>
                            @elseif (now()->greaterThan($fetchRequest->effectiveDeliveryDeadline()))
                                <p class="fetch-deadline-warning">The delivery deadline passed without an on-time delivery report.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section
            class="fetch-feed"
            aria-label="Live fetch requests"
            data-fetch-feed
            data-updates-url="{{ route('fetch.requests.updates') }}"
            data-last-id="{{ $requests->max('id') ?? 0 }}"
            data-accept-url-prefix="{{ url('/fetch/requests') }}"
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
                            <time
                                class="fetch-posted"
                                datetime="{{ $fetchRequest->created_at->toIso8601String() }}"
                                data-fetch-time-label="Posted"
                            >Posted {{ $fetchRequest->created_at->format('M j, g:i A') }} UTC</time>
                            <time
                                class="fetch-expiry"
                                datetime="{{ $fetchRequest->effectiveExpiresAt()->toIso8601String() }}"
                                data-fetch-time-label="Expires"
                            >Expires {{ $fetchRequest->effectiveExpiresAt()->format('M j, g:i A') }} UTC</time>
                            <time
                                class="fetch-expiry"
                                datetime="{{ $fetchRequest->effectiveDeliveryDeadline()->toIso8601String() }}"
                                data-fetch-time-label="Deliver by"
                            >Deliver by {{ $fetchRequest->effectiveDeliveryDeadline()->format('M j, g:i A') }} UTC</time>
                        </div>
                        @if ((int) $fetchRequest->user_id !== (int) auth()->id())
                            <form class="fetch-action-form" method="POST" action="{{ route('fetch.requests.accept', $fetchRequest) }}">
                                @csrf
                                <button class="button button-navy" name="accept" type="submit">Accept this request</button>
                            </form>
                        @else
                            <p class="fetch-own-request-label">This is your request.</p>
                        @endif
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
