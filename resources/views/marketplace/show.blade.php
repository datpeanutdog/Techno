@extends('layouts.app')

@section('title', $listing->title.' — PASABUY')

@section('content')
    <div class="detail-shell">
        <a class="back-link" href="{{ route('marketplace.index') }}">← Back to the marketplace</a>
        <div class="detail-layout">
            <div class="detail-image-wrap">
                @if ($listing->image_url)
                    <img class="detail-image" src="{{ $listing->image_url }}" alt="{{ $listing->title }}">
                @else
                    <div class="detail-image listing-placeholder" aria-hidden="true">✦</div>
                @endif
                <span class="listing-badge {{ $listing->type === 'rent' ? 'badge-rent' : '' }}">{{ $listing->type === 'rent' ? 'For rent' : 'For sale' }}</span>
            </div>
            <article class="detail-card">
                <div class="listing-card-topline">
                    <span class="listing-category">{{ $listing->category }}</span>
                    @if ($listing->condition)<span class="listing-condition">{{ $listing->condition }}</span>@endif
                </div>
                <h1>{{ $listing->title }}</h1>
                <p class="detail-price">₱{{ number_format((float) $listing->price) }}@if ($listing->type === 'rent') <span>/ {{ $listing->rent_period }}</span>@endif</p>
                <div class="detail-meta"><span>⌖</span> {{ $listing->location }} <span class="meta-dot">·</span> Listed {{ $listing->created_at->diffForHumans() }}</div>
                <hr>
                <h2>About this campus find</h2>
                <p class="detail-description">{{ $listing->description }}</p>
                <div class="seller-card">
                    <span class="seller-avatar">@if ($listing->user?->avatar_url)<img src="{{ $listing->user->avatar_url }}" alt="">@else{{ $listing->seller_initials }}@endif</span>
                    <span class="seller-info"><strong>{{ $listing->user?->name ?? $listing->seller_name }}</strong><span>{{ $listing->user?->campus ?? 'On PASABUY' }} <span class="seller-rating"><span aria-hidden="true">★</span> {{ number_format((float) $listing->seller_rating, 1) }}</span></span></span>
                    <span class="seller-verified" title="Community listing" aria-label="Community listing">✓</span>
                </div>
                @if ($listing->user?->bio)<p class="detail-description">{{ $listing->user->bio }}</p>@endif
                @if (auth()->id() === $listing->user_id)
                    <div class="my-listing-actions">
                        <a class="button button-navy" href="{{ route('my-listings.edit', $listing) }}">Edit listing</a>
                        <a class="button button-danger" href="{{ route('my-listings') }}">Manage my listings</a>
                    </div>
                @endif
                <div class="detail-safety">
                    <span aria-hidden="true">✳</span>
                    <p>Meet in a public campus spot, check the item, and agree on the details before paying or borrowing.</p>
                </div>
                <a class="button button-dark detail-back-button" href="{{ route('marketplace.index') }}">Keep browsing <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </div>
@endsection
