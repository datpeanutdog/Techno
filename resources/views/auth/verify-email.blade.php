@extends('layouts.app')

@section('title', 'Verify your email — PASABUY')

@section('content')
    <section class="auth-shell verification-shell">
        <div class="auth-card verification-card" data-email-verification data-status-url="{{ route('verification.status') }}">
            <div class="verification-state" data-verification-pending @if ($isVerified) hidden @endif>
                <span class="verification-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 7.5 12 13l8-5.5M5 6h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <p class="eyebrow">One quick step</p>
                <h1>Verify your email</h1>
                <p class="auth-intro">
                    We sent a verification link to <strong>{{ auth()->user()->email }}</strong>.
                    Open it to start posting listings and messaging sellers.
                </p>
                <p class="verification-status" data-verification-message role="status" aria-live="polite">
                    After verifying, return to this tab. This page will update automatically.
                </p>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="button button-navy form-submit" type="submit">Resend verification email <span aria-hidden="true">→</span></button>
                </form>
                <p class="auth-footnote"><a href="{{ route('marketplace.index') }}">Continue browsing</a></p>
            </div>

            <div class="verification-state" data-verification-complete @unless ($isVerified) hidden @endunless>
                <span class="verification-icon verification-icon-success" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="m5 12.5 4.5 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <p class="eyebrow">All set</p>
                <h1>Email verified</h1>
                <p class="auth-intro">
                    Your account is ready. You can continue in this tab or return to PASABUY in the tab opened from your email.
                </p>
                <a class="button button-navy verification-continue" href="{{ $continueUrl }}">Continue to PASABUY <span aria-hidden="true">→</span></a>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-button verification-logout" type="submit">Log out</button>
            </form>
        </div>
    </section>
@endsection
