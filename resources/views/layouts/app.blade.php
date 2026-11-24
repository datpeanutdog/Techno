<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Buy, sell, and rent school essentials with Pasabuy, the student-to-student campus marketplace.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Pasabuy — your neighborhood marketplace')</title>
    <script>
        try {
            document.documentElement.dataset.theme = window.localStorage.getItem('pasabuy:theme') === 'dark'
                ? 'dark'
                : 'light';
        } catch (error) {
            console.error('Unable to read the saved theme preference.', error);
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php($hasSidebar = auth()->check() && ! request()->routeIs('login', 'register', 'password.*', 'account.suspension.*'))
@php($isLandingPage = ! auth()->check() && request()->routeIs('marketplace.index'))
<body
    @auth
        @if (! auth()->user()->isSuspended())
            data-presence-heartbeat-url="{{ route('presence.heartbeat') }}"
        @endif
    @endauth
>
    <div class="site-layout {{ $hasSidebar ? 'has-sidebar' : '' }} {{ $isLandingPage ? 'landing-layout' : '' }}">
        @if ($hasSidebar)
            <aside class="site-sidebar" id="site-sidebar" aria-label="Site navigation">
                <div class="sidebar-heading">
                    <a class="brand sidebar-brand" href="{{ route('marketplace.index') }}" aria-label="Pasabuy home">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M7 11h18l-1.6 14H8.6L7 11Z" fill="currentColor"/>
                                <path d="M12 12V9a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                                <path d="m12 18 2.8 2.8 5.2-5.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="brand-word">Pasa<span>buy</span></span>
                    </a>
                    <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation menu">
                        <span aria-hidden="true">×</span>
                    </button>
                    <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-controls="site-sidebar" aria-expanded="true" aria-label="Hide sidebar" title="Hide sidebar">
                        <span aria-hidden="true">‹</span>
                    </button>
                </div>

                @auth
                    <a class="sidebar-profile-link" href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.edit')) aria-current="page" @endif>
                        @if (auth()->user()->avatar_url)
                            <img class="sidebar-profile-avatar" src="{{ auth()->user()->avatar_url }}" alt="" aria-hidden="true">
                        @else
                            <span class="sidebar-profile-avatar sidebar-profile-initial" aria-hidden="true">{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                        <span class="sidebar-profile-copy">
                            <span class="sidebar-profile-name">{{ auth()->user()->name }}</span>
                            <span class="sidebar-profile-action">View profile</span>
                        </span>
                    </a>
                @endauth

                <nav class="main-nav" aria-label="Main navigation">
                    <a class="{{ request()->routeIs('marketplace.index') && request('view') !== 'saved' && request('mode') !== 'rent' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['mode' => 'sale']) }}#discover" @if (request()->routeIs('marketplace.index') && request('view') !== 'saved' && request('mode') !== 'rent') aria-current="page" @endif>
                        <span>Browse marketplace</span>
                    </a>
                    <a class="{{ request()->routeIs('marketplace.index') && request('view') !== 'saved' && request('mode') === 'rent' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['mode' => 'rent']) }}#discover" @if (request()->routeIs('marketplace.index') && request('view') !== 'saved' && request('mode') === 'rent') aria-current="page" @endif>
                        <span>Browse rentals</span>
                    </a>
                    <a class="{{ request()->routeIs('marketplace.index') && request('view') === 'saved' ? 'is-active' : '' }}" href="{{ route('marketplace.index', ['view' => 'saved']) }}#discover" data-saved-link @if (request()->routeIs('marketplace.index') && request('view') === 'saved') aria-current="page" @endif>
                        <span>Saved finds</span>
                    </a>
                    <a class="{{ request()->routeIs('fetch.*') ? 'is-active' : '' }}" href="{{ route('fetch.index') }}" @if (request()->routeIs('fetch.*')) aria-current="page" @endif>
                        <span>Fetch request board</span>
                    </a>
                </nav>

                @auth
                    <p class="sidebar-section-label">Your account</p>
                    <nav class="account-nav" aria-label="Account navigation">
                        <a href="{{ route('my-listings') }}" @if (request()->routeIs('my-listings', 'my-listings.edit')) aria-current="page" @endif>
                            <span>Manage listings</span>
                        </a>
                        <a href="{{ route('listings.create') }}" @if (request()->routeIs('listings.create')) aria-current="page" @endif>
                            <span>List an item</span>
                        </a>
                        <a href="{{ route('conversations.index') }}" @if (request()->routeIs('conversations.*')) aria-current="page" @endif>
                            <span>Message inbox</span>
                        </a>
                        <a href="{{ route('settings.index') }}" @if (request()->routeIs('settings.index')) aria-current="page" @endif>
                            <span>Settings</span>
                        </a>
                    </nav>
                    <p class="sidebar-section-label">Help</p>
                    <nav class="account-nav" aria-label="Help navigation">
                        <a href="{{ route('support.reports.create') }}" @if (request()->routeIs('support.reports.create')) aria-current="page" @endif>
                            <span>Report an account</span>
                        </a>
                        <a href="{{ route('support.reports.index') }}" @if (request()->routeIs('support.reports.index')) aria-current="page" @endif>
                            <span>Contact support</span>
                        </a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('admin.support-reports.index') }}" @if (request()->routeIs('admin.support-reports.*')) aria-current="page" @endif>
                                <span>Admin tools</span>
                            </a>
                            <a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>
                                <span>Manage users</span>
                            </a>
                            <a href="{{ route('admin.suspension-appeals.index') }}" @if (request()->routeIs('admin.suspension-appeals.*')) aria-current="page" @endif>
                                <span>Suspension appeals</span>
                            </a>
                        @endif
                    </nav>
                    <div class="sidebar-footer">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="sidebar-logout-button" type="submit">
                                <span>Log out</span>
                            </button>
                        </form>
                    </div>
                @endauth

                @guest
                    <div class="sidebar-footer">
                        <p class="sidebar-section-label">Welcome</p>
                        <nav class="account-nav" aria-label="Account navigation">
                            <a href="{{ route('login') }}"><span>Log in</span></a>
                            <a class="account-register" href="{{ route('register') }}"><span>Join Pasabuy</span></a>
                        </nav>
                    </div>
                @endguest
            </aside>
            <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Close navigation menu"></button>
        @endif

        <div class="site-content">
            <header class="site-header">
                <div class="header-inner">
                    <a class="brand header-brand" href="{{ route('marketplace.index') }}" aria-label="Pasabuy home">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M7 11h18l-1.6 14H8.6L7 11Z" fill="currentColor"/>
                                <path d="M12 12V9a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
                                <path d="m12 18 2.8 2.8 5.2-5.2" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="brand-word">Pasa<span>buy</span></span>
                    </a>
                    @if ($hasSidebar)
                        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="site-sidebar" aria-expanded="true" aria-label="Hide sidebar">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                        @unless (request()->routeIs('marketplace.index', 'fetch.*', 'conversations.*', 'my-listings', 'my-listings.*', 'listings.create', 'profile.*', 'settings.*', 'support.reports.*'))
                            <a class="button button-navy header-sell" href="{{ route('listings.create') }}">
                                <span aria-hidden="true">＋</span> List an item
                            </a>
                        @endunless
                    @elseif (request()->routeIs('marketplace.index'))
                        <nav class="landing-header-nav" aria-label="Account navigation">
                            <a class="landing-header-login" href="{{ route('marketplace.index', ['panel' => 'login']) }}">Log in</a>
                            <a class="button button-navy landing-header-join" href="{{ route('marketplace.index') }}#account-form">Join Pasabuy <span aria-hidden="true">→</span></a>
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

        </div>
    </div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
