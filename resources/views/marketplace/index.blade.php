@extends('layouts.app')

@section('title', 'PASABUY — your campus marketplace')

@section('content')
    <section class="discover-section {{ $isSavedView ? 'saved-only-section' : '' }}" id="discover">
        @if ($isSavedView)
            <header class="saved-view-heading" data-saved-view data-favorites="{{ implode(',', $favoriteIds) }}">
                <span class="saved-view-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M6.75 3.75h10.5A1.75 1.75 0 0 1 19 5.5v15l-7-4.5-7 4.5v-15a1.75 1.75 0 0 1 1.75-1.75Z"/>
                    </svg>
                </span>
                <span class="saved-view-copy">
                    <h1>Save for later</h1>
                    <p>{{ $listings->total() }} {{ $listings->total() === 1 ? 'listing' : 'listings' }} saved on this device.</p>
                </span>
            </header>
        @endif

        @unless ($isSavedView)
        <div class="section-heading">
            <div>
                <h2>Find your kind of <span>campus essential.</span></h2>
            </div>
        </div>

        <form class="marketplace-filter-bar" method="GET" action="{{ route('marketplace.index') }}" aria-label="Search and filter campus listings">
            @foreach (['mode', 'location', 'sort', 'view', 'favorites'] as $filter)
                @if (request($filter)) <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}"> @endif
            @endforeach
            <label class="marketplace-search">
                <span class="marketplace-search-icon" aria-hidden="true">⌕</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search campus listings" aria-label="Search campus listings">
            </label>
            <label class="marketplace-category-filter">
                <span class="marketplace-category-label">Category</span>
                <select name="category" aria-label="Filter by category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-gold marketplace-search-submit" type="submit">Search</button>
        </form>

        <div class="listings-heading">
            <div>
                <a class="button button-navy listings-create-button" href="{{ route('listings.create') }}">
                    <span aria-hidden="true">＋</span> List an item
                </a>
            </div>
            <form class="sort-form" method="GET" action="{{ route('marketplace.index') }}">
                @foreach (['q', 'location', 'mode', 'category', 'view', 'favorites'] as $filter)
                    @if (request($filter)) <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}"> @endif
                @endforeach
                <label for="sort">Sort by</label>
                <select id="sort" name="sort">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>Just added</option>
                    <option value="price_low" @selected(request('sort') === 'price_low')>Price: low to high</option>
                    <option value="price_high" @selected(request('sort') === 'price_high')>Price: high to low</option>
                </select>
                <noscript><button class="sort-submit" type="submit">Apply</button></noscript>
            </form>
        </div>

        <nav class="market-tabs" aria-label="Browse buy and rent listings">
            <a class="market-mode-tab {{ ! $isSavedView && request('mode') !== 'rent' ? 'tab-active' : '' }}" href="{{ route('marketplace.index', array_filter(['category' => request('category'), 'q' => request('q'), 'location' => request('location')])) }}#discover" @if (! $isSavedView && request('mode') !== 'rent') aria-current="page" @endif>
                <span class="market-mode-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" focusable="false">
                        <path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h8.1a2 2 0 0 0 1.9-1.5L21.5 7H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="10" cy="19" r="1" fill="currentColor"/>
                        <circle cx="18" cy="19" r="1" fill="currentColor"/>
                    </svg>
                </span>
                <span class="market-mode-copy"><strong>Buy</strong><small>Find something to keep</small></span>
            </a>
            <a class="market-mode-tab {{ ! $isSavedView && request('mode') === 'rent' ? 'tab-active' : '' }}" href="{{ route('marketplace.index', array_filter(['mode' => 'rent', 'category' => request('category'), 'q' => request('q'), 'location' => request('location')])) }}#discover" @if (! $isSavedView && request('mode') === 'rent') aria-current="page" @endif>
                <span class="market-mode-icon market-mode-icon-rent" aria-hidden="true">↻</span>
                <span class="market-mode-copy"><strong>Rent</strong><small>Borrow it for a while</small></span>
            </a>
        </nav>
        @endunless

        @if (
            ! $isSavedView
            && (
                (request('q') !== null && request('q') !== '')
                || (request('location') !== null && request('location') !== '')
                || (request('mode') !== null && request('mode') !== '')
                || (request('category') !== null && request('category') !== '')
            )
        )
            <div class="active-filters">
                <span>Showing {{ $listings->total() }} {{ $listings->total() === 1 ? 'campus find' : 'campus finds' }}</span>
                <a href="{{ route('marketplace.index') }}#discover">Clear filters <span aria-hidden="true">×</span></a>
            </div>
        @endif

        @if ($listings->isNotEmpty())
            <div class="listing-grid">
                @foreach ($listings as $listing)
                    <article class="listing-card">
                        <div class="listing-image-wrap">
                            <a class="listing-image-link" href="{{ route('listings.show', $listing) }}" aria-label="View {{ $listing->title }}">
                                @if ($listing->image_url)
                                    <img class="listing-image" src="{{ $listing->image_url }}" alt="{{ $listing->title }}" loading="lazy" referrerpolicy="no-referrer">
                                @else
                                    <div class="listing-image listing-placeholder" aria-hidden="true">✦</div>
                                @endif
                            </a>
                            <span class="listing-badge {{ $listing->type === 'rent' ? 'badge-rent' : '' }}">{{ $listing->type === 'rent' ? 'For rent' : 'For sale' }}</span>
                            <button class="favorite-button" type="button" data-favorite="{{ $listing->id }}" aria-label="Save {{ $listing->title }}" aria-pressed="false">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.75 3.75h10.5A1.75 1.75 0 0 1 19 5.5v15l-7-4.5-7 4.5v-15a1.75 1.75 0 0 1 1.75-1.75Z"/></svg>
                            </button>
                        </div>
                        <div class="listing-details">
                            <div class="listing-card-topline">
                                <span class="listing-category">{{ $listing->category }}</span>
                                @if ($listing->condition)<span class="listing-condition condition-{{ Illuminate\Support\Str::slug($listing->condition) }}">{{ $listing->condition }}</span>@endif
                            </div>
                            <a class="listing-title" href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a>
                            <div class="listing-meta"><span aria-hidden="true">⌖</span> {{ $listing->location }}</div>
                            @if ($listing->user)
                                <a class="seller-profile-link" href="{{ route('sellers.show', $listing->user) }}">
                                    {{ $listing->user->name }}
                                    @if ($listing->user->received_reviews_count)
                                        · ★ {{ number_format((float) $listing->user->received_reviews_avg_rating, 1) }}
                                    @else
                                        · View seller
                                    @endif
                                </a>
                            @endif
                            <time class="listing-posted" datetime="{{ $listing->created_at->toIso8601String() }}">Listed {{ $listing->created_at->diffForHumans() }}</time>
                            <div class="listing-card-bottom">
                                <p class="listing-price">₱{{ number_format((float) $listing->price, $listing->price == (int) $listing->price ? 0 : 2) }}@if ($listing->type === 'rent')<span> / {{ $listing->rent_period }}</span>@endif</p>
                                <span class="seller-rating">
                                    @if ($listing->user)
                                        @if ($listing->user->received_reviews_count)
                                            <span aria-hidden="true">★</span> {{ number_format((float) $listing->user->received_reviews_avg_rating, 1) }}
                                        @else
                                            New seller
                                        @endif
                                    @else
                                        <span aria-hidden="true">★</span> {{ number_format((float) $listing->seller_rating, 1) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pagination-wrap">{{ $listings->links() }}</div>
        @else
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">
                    @if ($isSavedView)
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6.75 3.75h10.5A1.75 1.75 0 0 1 19 5.5v15l-7-4.5-7 4.5v-15a1.75 1.75 0 0 1 1.75-1.75Z"/>
                        </svg>
                    @else
                        ⌕
                    @endif
                </span>
                <h3>{{ $isSavedView ? 'No saved items yet.' : 'No campus finds just yet.' }}</h3>
                <p>{{ $isSavedView ? 'Bookmark a listing and it will be waiting here.' : 'Try a different search or clear your filters. Your next school-day essential is out there.' }}</p>
                <a class="button button-navy" href="{{ route('marketplace.index') }}#discover">{{ $isSavedView ? 'Browse campus finds' : 'See all school finds' }}</a>
            </div>
        @endif
    </section>

@endsection
