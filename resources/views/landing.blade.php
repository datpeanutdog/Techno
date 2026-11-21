@extends('layouts.app')

@section('title', 'PASABUY — made for USTP student life')

@section('content')
    <section class="landing-hero" aria-labelledby="landing-title">
        <img class="landing-hero-image" src="{{ asset('images/ustp-campus.jpg') }}" alt="" fetchpriority="high">
        <div class="landing-hero-shade"></div>
        <div class="landing-hero-grid">
            <div class="landing-hero-copy">
                <p class="eyebrow"><span aria-hidden="true">✦</span> USTP student marketplace</p>
                <h1 id="landing-title">Buy. Sell.<br><span>Rent.</span></h1>
                <p class="landing-description">
                    Find and share school essentials with fellow USTP students.
                </p>
            </div>

            @if (request('panel') === 'login')
                <form class="landing-signup-card" method="POST" action="{{ route('login') }}" aria-labelledby="landing-login-title">
                    @csrf
                    <p class="eyebrow">Welcome back</p>
                    <h2 id="landing-login-title">Log in</h2>
                    <p class="landing-signup-intro">Sign in to browse campus finds.</p>

                    @if ($errors->any())
                        <div class="form-errors" role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <label class="form-label" for="landing-login-email">Email address</label>
                    <input class="form-control" id="landing-login-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>

                    <label class="form-label" for="landing-login-password">Password</label>
                    <input class="form-control" id="landing-login-password" name="password" type="password" autocomplete="current-password" required>

                    <label class="remember-label"><input type="checkbox" name="remember"> Keep me signed in</label>
                    <button class="button button-navy landing-signup-submit" type="submit">Log in <span aria-hidden="true">→</span></button>
                    <a class="landing-support-link" href="{{ route('support.reports.index') }}">
                        <span class="landing-support-icon" aria-hidden="true">?</span>
                        <span><strong>Need help?</strong><small>Contact support</small></span>
                        <span class="landing-support-arrow" aria-hidden="true">→</span>
                    </a>
                    <p class="auth-footnote"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
                    <p class="auth-footnote">New to PASABUY? <a href="{{ route('marketplace.index') }}">Create an account</a></p>
                </form>
            @else
                <form class="landing-signup-card" id="account-form" method="POST" action="{{ route('register') }}" aria-labelledby="landing-signup-title">
                    @csrf
                    <p class="eyebrow">Join the campus marketplace</p>
                    <h2 id="landing-signup-title">Create your account</h2>
                    <p class="landing-signup-intro">Sign up to browse campus finds.</p>

                    @if ($errors->any())
                        <div class="form-errors" role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <label class="form-label" for="landing-name">Full name</label>
                    <input class="form-control" id="landing-name" name="name" value="{{ old('name') }}" maxlength="60" autocomplete="name" required>

                    <label class="form-label" for="landing-email">Email address</label>
                    <input class="form-control" id="landing-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>

                    <label class="form-label" for="landing-password">Password</label>
                    <input class="form-control" id="landing-password" name="password" type="password" autocomplete="new-password" required>

                    <label class="form-label" for="landing-password-confirmation">Confirm password</label>
                    <input class="form-control" id="landing-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                    <button class="button button-navy landing-signup-submit" type="submit">Create account <span aria-hidden="true">→</span></button>
                    <a class="landing-support-link" href="{{ route('support.reports.index') }}">
                        <span class="landing-support-icon" aria-hidden="true">?</span>
                        <span><strong>Need help?</strong><small>Contact support</small></span>
                        <span class="landing-support-arrow" aria-hidden="true">→</span>
                    </a>
                    <p class="auth-footnote">Already have an account? <a href="{{ route('marketplace.index', ['panel' => 'login']) }}">Log in</a></p>
                </form>
            @endif
        </div>
    </section>
@endsection
