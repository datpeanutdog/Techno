@extends('layouts.app')

@section('title', 'PASABUY — your campus marketplace')

@section('content')
    <section class="search-wrap" aria-label="Find a school listing">
        <form class="search-form" method="GET" action="{{ route('marketplace.index') }}">
            @if (request('mode')) <input type="hidden" name="mode" value="{{ request('mode') }}"> @endif
            @if (request('category')) <input type="hidden" name="category" value="{{ request('category') }}"> @endif
            @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
            <label class="search-field search-keyword">
                <span class="search-icon" aria-hidden="true">⌕</span>
                <span class="search-field-copy">
                    <span class="field-label">What do you need for school?</span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Try &quot;calculator&quot; or &quot;biology notes&quot;" aria-label="Search school listings">
                </span>
            </label>
            <span class="search-divider" aria-hidden="true"></span>
            <label class="search-field search-location">
                <span class="search-icon location-icon" aria-hidden="true">⌖</span>
                <span class="search-field-copy">
                    <span class="field-label">Where's your campus?</span>
                    <input type="text" name="location" value="{{ request('location') }}" placeholder="Campus or neighborhood" aria-label="Filter by campus or pickup location">
                </span>
            </label>
            <button class="button button-gold search-button" type="submit"><span aria-hidden="true">⌕</span> Search</button>
        </form>
    </section>

    <section class="discover-section" id="discover">
        <div class="section-heading">
            <div>
                <p class="eyebrow eyebrow-small">A good place to start</p>
                <h2>Find your kind of <span>campus essential.</span></h2>
            </div>
            <p class="section-aside">The next student who needs it could be right around the corner.</p>
        </div>

        <div class="category-list" aria-label="Browse school categories">
            @php
                $categoryIcons = [
                    'Books & Notes' => '▤',
                    'School Supplies' => '✎',
                    'Tech & Calculators' => '▦',
                    'Uniforms' => '♧',
                    'Dorm Essentials' => '⌂',
                    'Campus Services' => '✦',
                ];
            @endphp
            <a class="category-pill {{ !request('category') ? 'category-selected' : '' }}" href="{{ route('marketplace.index', array_filter(['mode' => request('mode')])) }}#discover">
                <span class="category-icon category-all">✦</span> Everything
            </a>
            @foreach ($categories as $category)
                <a class="category-pill {{ request('category') === $category ? 'category-selected' : '' }}" href="{{ route('marketplace.index', array_filter(['mode' => request('mode'), 'category' => $category])) }}#discover">
                    <span class="category-icon">{{ $categoryIcons[$category] }}</span> {{ $category }}
                </a>
            @endforeach
        </div>

        <div class="listings-heading">
            <div>
                <p class="eyebrow eyebrow-small">{{ request('mode') === 'rent' ? 'Borrow it for class, keep your budget' : 'Passed on by students, ready for class' }}</p>
                <h2>{{ request('mode') === 'rent' ? 'Things to rent for school.' : 'The latest campus finds.' }}</h2>
            </div>
            <form class="sort-form" method="GET" action="{{ route('marketplace.index') }}">
                @foreach (['q', 'location', 'mode', 'category'] as $filter)
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

        <nav class="market-tabs" aria-label="Browse sale and rental listings">
            <a class="market-mode-tab {{ request('mode') !== 'rent' ? 'tab-active' : '' }}" href="{{ route('marketplace.index', array_filter(['category' => request('category'), 'q' => request('q'), 'location' => request('location')])) }}#discover" @if (request('mode') !== 'rent') aria-current="page" @endif>
                <span class="market-mode-icon" aria-hidden="true">↗</span>
                <span class="market-mode-copy"><strong>Buy</strong><small>Find something to keep</small></span>
            </a>
            <a class="market-mode-tab {{ request('mode') === 'rent' ? 'tab-active' : '' }}" href="{{ route('marketplace.index', array_filter(['mode' => 'rent', 'category' => request('category'), 'q' => request('q'), 'location' => request('location')])) }}#discover" @if (request('mode') === 'rent') aria-current="page" @endif>
                <span class="market-mode-icon market-mode-icon-rent" aria-hidden="true">↻</span>
                <span class="market-mode-copy"><strong>Rent</strong><small>Borrow it for a while</small></span>
            </a>
            <a class="market-sell-link" href="{{ route('listings.create') }}">List an item <span aria-hidden="true">＋</span></a>
        </nav>

        @if (request()->hasAny(['q', 'location', 'mode', 'category']))
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
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"/></svg>
                            </button>
                        </div>
                        <div class="listing-details">
                            <div class="listing-card-topline">
                                <span class="listing-category">{{ $listing->category }}</span>
                                @if ($listing->condition)<span class="listing-condition">{{ $listing->condition }}</span>@endif
                            </div>
                            <a class="listing-title" href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a>
                            <div class="listing-meta"><span aria-hidden="true">⌖</span> {{ $listing->location }}</div>
                            <div class="listing-card-bottom">
                                <p class="listing-price">₱{{ number_format((float) $listing->price, $listing->price == (int) $listing->price ? 0 : 2) }}@if ($listing->type === 'rent')<span> / {{ $listing->rent_period }}</span>@endif</p>
                                <span class="seller-rating"><span aria-hidden="true">★</span> {{ number_format((float) $listing->seller_rating, 1) }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pagination-wrap">{{ $listings->links() }}</div>
        @else
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">⌕</span>
                <h3>No campus finds just yet.</h3>
                <p>Try a different search or clear your filters. Your next school-day essential is out there.</p>
                <a class="button button-navy" href="{{ route('marketplace.index') }}#discover">See all school finds</a>
            </div>
        @endif
    </section>

    <section class="sell-banner">
        <div class="sell-banner-mark" aria-hidden="true">✳</div>
        <div>
            <p class="eyebrow">Good things travel campus to campus</p>
            <h2>Finished the semester?<br>Pass your school finds on.</h2>
            <p class="sell-banner-copy">Your books and supplies could be someone's next great start.</p>
        </div>
        <a class="button button-navy" href="{{ route('listings.create') }}">List a school find <span aria-hidden="true">→</span></a>
    </section>
@endsection
