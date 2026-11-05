<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Buy, sell, and rent school essentials with PASABUY, the student-to-student campus marketplace.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'PASABUY — your neighborhood marketplace')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="announcement-bar">
        <span>Good school finds deserve a second semester.</span>
        <span class="announcement-location"><span aria-hidden="true">✦</span> Made for student life</span>
    </div>

    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="{{ route('marketplace.index') }}" aria-label="PASABUY home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M7 11h18l-1.6 14H8.6L7 11Z" fill="currentColor"/>
                        <path d="M12 12V9a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                        <path d="m12 18 2.8 2.8 5.2-5.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="brand-word">pasa<span>buy</span></span>
            </a>

            @unless (request()->routeIs('login', 'register', 'password.*'))
                <nav class="main-nav" aria-label="Main navigation">
                    <a class="{{ request()->routeIs('marketplace.index') && request('mode') !== 'rent' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['mode' => 'sale']) }}#discover" @if (request()->routeIs('marketplace.index') && request('mode') !== 'rent') aria-current="page" @endif>Buy</a>
                    <a class="{{ request()->routeIs('marketplace.index') && request('mode') === 'rent' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['mode' => 'rent']) }}#discover" @if (request()->routeIs('marketplace.index') && request('mode') === 'rent') aria-current="page" @endif>Rent</a>
                    <a class="{{ request()->routeIs('listings.create') ? 'is-active' : '' }}" href="{{ route('listings.create') }}" @if (request()->routeIs('listings.create')) aria-current="page" @endif>Sell</a>
                </nav>

                <a class="button button-navy header-sell" href="{{ route('listings.create') }}">
                    <span aria-hidden="true">＋</span> List an item
                </a>
                @auth
                    <div class="account-nav">
                        <a href="{{ route('conversations.index') }}" @if (request()->routeIs('conversations.*')) aria-current="page" @endif>Messages</a>
                        <a href="{{ route('profile.edit') }}">{{ auth()->user()->name }}</a>
                        <a href="{{ route('my-listings') }}">My listings</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
                    </div>
                @endauth
                @guest
                    <div class="account-nav">
                        <a href="{{ route('login') }}">Log in</a>
                        <a class="account-register" href="{{ route('register') }}">Join PASABUY</a>
                    </div>
                @endguest
            @endunless
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="flash-message" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <a class="brand footer-brand" href="{{ route('marketplace.index') }}">
                <span class="brand-mark" aria-hidden="true">✳</span>
                <span class="brand-word">pasa<span>buy</span></span>
            </a>
            <p>Good finds, shared by students like you.</p>
            <span class="footer-note">Buy · sell · rent · pass it on</span>
        </div>
    </footer>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
