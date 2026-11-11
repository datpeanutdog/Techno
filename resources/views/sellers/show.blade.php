@extends('layouts.app')

@section('title', $seller->name.' — seller profile | PASABUY')

@section('content')
    @php
        $avatarUrl = $seller->avatar_url;
    @endphp
    <div class="seller-profile-shell">
        <a class="back-link" href="{{ route('marketplace.index') }}">← Back to the marketplace</a>

        <section class="seller-profile-card">
            <div class="seller-profile-avatar">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $seller->name }}'s profile photo">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($seller->name, 0, 1)) }}
                @endif
            </div>
            <div class="seller-profile-copy">
                <p class="eyebrow">PASABUY student seller</p>
                <h1>{{ $seller->name }}</h1>
                <p class="seller-profile-campus">{{ $seller->campus ?: 'Campus community member' }}</p>
                @if ($seller->bio)
                    <p class="seller-profile-bio">{{ $seller->bio }}</p>
                @endif
                <div class="seller-profile-stats">
                    <span><strong>{{ $seller->active_listings_count }}</strong> active {{ \Illuminate\Support\Str::plural('listing', $seller->active_listings_count) }}</span>
                    <span>
                        @if ($seller->received_reviews_count)
                            <strong aria-label="{{ number_format((float) $seller->received_reviews_avg_rating, 1) }} out of 5 stars">★ {{ number_format((float) $seller->received_reviews_avg_rating, 1) }}</strong>
                            {{ $seller->received_reviews_count }} {{ \Illuminate\Support\Str::plural('review', $seller->received_reviews_count) }}
                        @else
                            No reviews yet
                        @endif
                    </span>
                </div>
            </div>
        </section>

        <section class="seller-profile-section" aria-labelledby="seller-listings-title">
            <div class="seller-section-heading">
                <div>
                    <p class="eyebrow eyebrow-small">Shared by {{ $seller->name }}</p>
                    <h2 id="seller-listings-title">Active listings</h2>
                </div>
            </div>
            @if ($listings->isNotEmpty())
                <div class="listing-grid seller-listing-grid">
                    @foreach ($listings as $listing)
                        <article class="listing-card">
                            <a class="listing-image-link" href="{{ route('listings.show', $listing) }}">
                                @if ($listing->image_url)
                                    <img class="listing-image" src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy" referrerpolicy="no-referrer">
                                @else
                                    <span class="listing-image listing-placeholder" aria-hidden="true">✦</span>
                                @endif
                            </a>
                            <div class="listing-details">
                                <span class="listing-category">{{ $listing->category }}</span>
                                <a class="listing-title" href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a>
                                <div class="listing-meta"><span aria-hidden="true">⌖</span> {{ $listing->location }}</div>
                                <div class="listing-card-bottom">
                                    <p class="listing-price">₱{{ number_format((float) $listing->price, $listing->price == (int) $listing->price ? 0 : 2) }}@if ($listing->type === 'rent')<span> / {{ $listing->rent_period }}</span>@endif</p>
                                    <span class="listing-badge {{ $listing->type === 'rent' ? 'badge-rent' : '' }}">{{ $listing->type === 'rent' ? 'For rent' : 'For sale' }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="pagination-wrap">{{ $listings->links() }}</div>
            @else
                <p class="seller-empty-state">There are no active listings right now.</p>
            @endif
        </section>

        <section class="seller-profile-section seller-reviews-section" aria-labelledby="seller-reviews-title">
            <div class="seller-section-heading">
                <div>
                    <p class="eyebrow eyebrow-small">Student feedback</p>
                    <h2 id="seller-reviews-title">Reviews &amp; ratings</h2>
                </div>
                @if ($seller->received_reviews_count)
                    <p class="seller-section-rating"><span aria-hidden="true">★</span> {{ number_format((float) $seller->received_reviews_avg_rating, 1) }} / 5</p>
                @endif
            </div>

            @if (auth()->check() && auth()->id() !== $seller->id)
                <form class="seller-review-form" method="POST" action="{{ route('sellers.reviews.store', $seller) }}">
                    @csrf
                    <h3>{{ $myReview ? 'Update your review' : 'Share your experience' }}</h3>
                    <p>Your rating helps other students know what to expect. You can update your review later.</p>
                    @if ($errors->any())
                        <div class="form-errors" role="alert">
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <fieldset class="seller-star-picker">
                        <legend class="form-label">Your rating</legend>
                        <div class="seller-star-options">
                            @for ($rating = 5; $rating >= 1; $rating--)
                                <input
                                    id="seller-rating-{{ $rating }}"
                                    type="radio"
                                    name="rating"
                                    value="{{ $rating }}"
                                    @checked((int) old('rating', $myReview?->rating) === $rating)
                                    required
                                >
                                <label for="seller-rating-{{ $rating }}" aria-label="{{ $rating }} {{ \Illuminate\Support\Str::plural('star', $rating) }}">★</label>
                            @endfor
                        </div>
                    </fieldset>
                    <label class="form-label" for="seller-review-body">Your review</label>
                    <textarea class="form-control" id="seller-review-body" name="body" rows="4" maxlength="1000" placeholder="How was your experience with this seller?" required>{{ old('body', $myReview?->body) }}</textarea>
                    <button class="button button-navy form-submit" type="submit">{{ $myReview ? 'Update review' : 'Post review' }} <span aria-hidden="true">→</span></button>
                </form>
            @elseif (!auth()->check())
                <p class="seller-review-signin"><a href="{{ route('login') }}">Log in</a> to leave a review for this seller.</p>
            @endif

            @if ($reviews->isNotEmpty())
                <div class="seller-review-list">
                    @foreach ($reviews as $review)
                        <article class="seller-review">
                            <div class="seller-review-avatar">
                                @if ($review->reviewer->avatar_url)
                                    <img src="{{ $review->reviewer->avatar_url }}" alt="">
                                @else
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($review->reviewer->name, 0, 1)) }}
                                @endif
                            </div>
                            <div class="seller-review-copy">
                                <div class="seller-review-heading">
                                    <strong>{{ $review->reviewer->name }}</strong>
                                    <span class="seller-review-date">{{ $review->created_at->format('M j, Y') }}</span>
                                </div>
                                @if ($review->reviewer->campus)<p class="seller-review-campus">{{ $review->reviewer->campus }}</p>@endif
                                <p class="seller-review-stars" aria-label="{{ $review->rating }} out of 5 stars">@for ($star = 1; $star <= 5; $star++)<span class="{{ $star <= $review->rating ? 'is-filled' : '' }}">★</span>@endfor</p>
                                <p class="seller-review-body">{{ $review->body }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="pagination-wrap">{{ $reviews->links() }}</div>
            @else
                <p class="seller-empty-state">No reviews yet. Be the first to share feedback.</p>
            @endif
            <p class="seller-review-note">Reviews are submitted by PASABUY members and are not independently verified as completed transactions.</p>
        </section>
    </div>
@endsection
