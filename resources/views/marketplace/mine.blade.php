@extends('layouts.app')

@section('title', 'My listings — PASABUY')

@section('content')
    <section class="my-listings-shell">
        <p class="eyebrow">Your campus marketplace</p>
        <h1>My listings</h1>
        <p class="auth-intro">Update availability, details, or remove an item you’ve passed on.</p>
        @forelse ($listings as $listing)
            <article class="my-listing">
                <div>
                    <span class="listing-category">{{ $listing->category }} · {{ $listing->type === 'rent' ? 'For rent' : 'For sale' }}</span>
                    <h2><a href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a></h2>
                    <p>₱{{ number_format((float) $listing->price, 2) }} · {{ $listing->location }}</p>
                    <time class="listing-added" datetime="{{ $listing->created_at->toIso8601String() }}" title="{{ $listing->created_at->format('M j, Y \a\t g:i A') }}">Listed {{ $listing->created_at->diffForHumans() }}</time>
                </div>
                <div class="my-listing-actions">
                    <a class="button button-navy" href="{{ route('my-listings.edit', $listing) }}">Edit</a>
                    <form method="POST" action="{{ route('my-listings.destroy', $listing) }}" onsubmit="return confirm('Remove this listing?')">
                        @csrf @method('DELETE')
                        <button class="button button-danger" type="submit">Remove</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="auth-card"><h2>No listings yet</h2><p>Your next school-day essential can go here.</p><a class="button button-navy" href="{{ route('listings.create') }}">Post an item</a></div>
        @endforelse
        <div class="pagination-wrap">{{ $listings->links() }}</div>
    </section>
@endsection
