@extends('layouts.app')

@section('title', 'Settings — PASABUY')

@section('content')
    <section class="settings-shell">
        <header class="settings-header">
            <p class="eyebrow">Make PASABUY yours</p>
            <h1>Settings</h1>
            <p class="settings-intro">Choose how PASABUY looks on this device and find your account tools.</p>
        </header>

        <div class="settings-grid">
            <section class="settings-card" aria-labelledby="appearance-heading">
                <p class="eyebrow">Comfortable browsing</p>
                <h2 id="appearance-heading">Appearance</h2>
                <fieldset class="settings-theme-options" data-theme-settings>
                    <legend class="sr-only">Choose a color theme</legend>
                    <label class="settings-theme-option" for="theme-system">
                        <span class="settings-theme-label">System</span>
                        <input id="theme-system" type="radio" name="theme" value="system" data-theme-option checked>
                    </label>
                    <label class="settings-theme-option" for="theme-light">
                        <span class="settings-theme-label">Light</span>
                        <input id="theme-light" type="radio" name="theme" value="light" data-theme-option>
                    </label>
                    <label class="settings-theme-option" for="theme-dark">
                        <span class="settings-theme-label">Dark</span>
                        <input id="theme-dark" type="radio" name="theme" value="dark" data-theme-option>
                    </label>
                </fieldset>
                <p class="settings-status" data-theme-status role="status" aria-live="polite">Your theme preference is saved on this browser.</p>
            </section>

            <section class="settings-card" aria-labelledby="account-tools-heading">
                <p class="eyebrow">Your account</p>
                <h2 id="account-tools-heading">Account tools</h2>
                <div class="settings-links">
                    <a class="settings-link" href="{{ route('profile.edit') }}">
                        <span>
                            <strong>Profile settings</strong>
                            <small>Update your name, campus, bio, or photo.</small>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>
                    <a class="settings-link" href="{{ route('support.reports.create') }}">
                        <span>
                            <strong>Help and support</strong>
                            <small>Get help with your PASABUY account.</small>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </section>
        </div>
    </section>
@endsection
