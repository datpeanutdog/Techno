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
@php($hasSidebar = auth()->check() && ! request()->routeIs('login', 'register', 'password.*', 'account.suspension.*'))
@php($isLandingPage = ! auth()->check() && request()->routeIs('marketplace.index'))
<body>
    <div class="site-layout {{ $hasSidebar ? 'has-sidebar' : '' }} {{ $isLandingPage ? 'landing-layout' : '' }}">
        @if ($hasSidebar)
            <aside class="site-sidebar" id="site-sidebar" aria-label="Site navigation">
                <div class="sidebar-heading">
                    <a class="brand sidebar-brand" href="{{ route('marketplace.index') }}" aria-label="PASABUY home">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M7 11h18l-1.6 14H8.6L7 11Z" fill="currentColor"/>
                                <path d="M12 12V9a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                                <path d="m12 18 2.8 2.8 5.2-5.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="brand-word">pasa<span>buy</span></span>
                    </a>
                    <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation menu">
                        <span aria-hidden="true">×</span>
                    </button>
                    <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-controls="site-sidebar" aria-expanded="true" aria-label="Hide sidebar" title="Hide sidebar">
                        <span aria-hidden="true">‹</span>
                    </button>
                </div>

                <p class="sidebar-section-label">Marketplace</p>
                <nav class="main-nav" aria-label="Main navigation">
                    <a class="{{ request()->routeIs('marketplace.index') && request('mode') !== 'rent' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['mode' => 'sale']) }}#discover" @if (request()->routeIs('marketplace.index') && request('mode') !== 'rent') aria-current="page" @endif>
                        <span class="sidebar-nav-icon" aria-hidden="true">⌕</span><span>Buy</span>
                    </a>
                    <a class="{{ request()->routeIs('marketplace.index') && request('mode') === 'rent' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['mode' => 'rent']) }}#discover" @if (request()->routeIs('marketplace.index') && request('mode') === 'rent') aria-current="page" @endif>
                        <span class="sidebar-nav-icon" aria-hidden="true">↗</span><span>Rent</span>
                    </a>
                    <a class="{{ request()->routeIs('listings.create') ? 'is-active' : '' }}" href="{{ route('listings.create') }}" @if (request()->routeIs('listings.create')) aria-current="page" @endif>
                        <span class="sidebar-nav-icon" aria-hidden="true">＋</span><span>Sell</span>
                    </a>
                </nav>

                @auth
                    <p class="sidebar-section-label">Your account</p>
                    <nav class="account-nav" aria-label="Account navigation">
                        <a href="{{ route('conversations.index') }}" @if (request()->routeIs('conversations.*')) aria-current="page" @endif>
                            <span class="sidebar-nav-icon" aria-hidden="true">✉</span><span>Messages</span>
                        </a>
                        <a class="support-nav-link" href="{{ route('support.reports.mine') }}" @if (request()->routeIs('support.reports.*')) aria-current="page" @endif>
                            <span class="sidebar-nav-icon" aria-hidden="true">?</span><span>Support</span>
                        </a>
                        <a href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.edit')) aria-current="page" @endif>
                            <span class="sidebar-nav-icon" aria-hidden="true">☺</span><span>{{ auth()->user()->name }}</span>
                        </a>
                        <a href="{{ route('my-listings') }}" @if (request()->routeIs('my-listings')) aria-current="page" @endif>
                            <span class="sidebar-nav-icon" aria-hidden="true">▤</span><span>My listings</span>
                        </a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.support-reports.index') }}" @if (request()->routeIs('admin.support-reports.*')) aria-current="page" @endif>
                                <span class="sidebar-nav-icon" aria-hidden="true">⚙</span><span>Admin</span>
                            </a>
                            <a href="{{ route('admin.suspension-appeals.index') }}" @if (request()->routeIs('admin.suspension-appeals.*')) aria-current="page" @endif>
                                <span class="sidebar-nav-icon" aria-hidden="true">⚖</span><span>Appeals</span>
                            </a>
                        @endif
                    </nav>
                    <div class="sidebar-footer">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="sidebar-logout-button" type="submit">
                                <span class="sidebar-nav-icon" aria-hidden="true">⇥</span><span>Log out</span>
                            </button>
                        </form>
                        <p class="sidebar-caption">PASABUY · YOUR CAMPUS COMMUNITY</p>
                    </div>
                @endauth

                @guest
                    <div class="sidebar-footer">
                        <p class="sidebar-section-label">Welcome</p>
                        <nav class="account-nav" aria-label="Account navigation">
                            <a href="{{ route('login') }}"><span class="sidebar-nav-icon" aria-hidden="true">⇥</span><span>Log in</span></a>
                            <a class="account-register" href="{{ route('register') }}"><span class="sidebar-nav-icon" aria-hidden="true">＋</span><span>Join PASABUY</span></a>
                        </nav>
                        <p class="sidebar-caption">PASABUY · YOUR CAMPUS COMMUNITY</p>
                    </div>
                @endguest
            </aside>
            <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Close navigation menu"></button>
        @endif

        <div class="site-content">
            <div class="announcement-bar">
                <span>Good school finds deserve a second semester.</span>
                <span class="announcement-location"><span aria-hidden="true">✦</span> Made for student life</span>
            </div>

            <header class="site-header">
                <div class="header-inner">
                    <a class="brand header-brand" href="{{ route('marketplace.index') }}" aria-label="PASABUY home">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M7 11h18l-1.6 14H8.6L7 11Z" fill="currentColor"/>
                                <path d="M12 12V9a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                                <path d="m12 18 2.8 2.8 5.2-5.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="brand-word">pasa<span>buy</span></span>
                    </a>
                    @if ($hasSidebar)
                        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="site-sidebar" aria-expanded="true" aria-label="Hide sidebar">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                        <a class="button button-navy header-sell" href="{{ route('listings.create') }}">
                            <span aria-hidden="true">＋</span> List an item
                        </a>
                    @elseif (request()->routeIs('marketplace.index'))
                        <nav class="landing-header-nav" aria-label="Account navigation">
                            <a class="landing-header-login" href="{{ route('marketplace.index', ['panel' => 'login']) }}">Log in</a>
                            <a class="button button-navy landing-header-join" href="{{ route('marketplace.index') }}#account-form">Join PASABUY <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>
            </header>

            <main>
                @if (session('status'))
                    <div class="flash-message" role="status">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>

            @unless ($isLandingPage)
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
            @endunless
        </div>
    </div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
