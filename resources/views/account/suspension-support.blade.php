@extends('layouts.app')

@section('title', 'Account support — PASABUY')

@section('content')
    <div class="support-shell">
        <section class="support-card suspension-support-card">
            <p class="eyebrow">CUSTOMER SERVICE</p>
            <h1>Your account is suspended</h1>
            <p class="support-intro">You can’t use your account while this suspension is active. Review the reason below and contact our support team if you believe this was a mistake.</p>

            <nav class="support-tabs" aria-label="Account support">
                <a href="#suspension-details">Suspension details</a>
                <a href="#appeal">Support &amp; appeal</a>
            </nav>

            <div class="suspension-reason" id="suspension-details" role="status">
                <strong>Reason for suspension</strong>
                <p>{{ $user->suspension_reason ?: 'No additional reason was provided. Please contact support for clarification.' }}</p>
            </div>

            <p class="support-report-meta">
                <span>Account ID: {{ $user->account_identifier }}</span>
                <span>Suspended {{ $user->suspended_at?->format('M j, Y') }}</span>
            </p>

            @if ($appeal?->previousAppeal)
                <article class="suspension-appeal-status appeal-history-card">
                    <div class="support-report-topline">
                        <strong>Response to your previous appeal</strong>
                        <span class="support-status status-{{ $appeal->previousAppeal->status }}">{{ str($appeal->previousAppeal->status)->replace('_', ' ')->title() }}</span>
                    </div>
                    <h2 class="support-section-title">Your previous message</h2>
                    <p class="support-report-details">{{ $appeal->previousAppeal->message }}</p>
                    <h2 class="support-section-title">Support response</h2>
                    <p class="support-admin-note">{{ $appeal->previousAppeal->admin_note ?: 'No response message was included with the decision. You can explain what information or image was missing in your counter-appeal.' }}</p>
                </article>
            @endif

            @if ($appeal)
                <article class="suspension-appeal-status">
                    <div class="support-report-topline">
                        <strong>{{ $appeal->previousAppeal ? 'Your counter-appeal' : ($appeal->status === 'denied' ? 'Your previous appeal' : 'Your latest appeal') }}</strong>
                        <span class="support-status status-{{ $appeal->status }}">{{ str($appeal->status)->replace('_', ' ')->title() }}</span>
                    </div>
                    <p class="support-report-details">{{ $appeal->message }}</p>
                    @if ($appeal->proof_path)
                        <a class="appeal-proof-link" href="{{ route('account.suspension.appeals.proof', $appeal) }}" target="_blank" rel="noopener">
                            <img class="appeal-proof-image" src="{{ route('account.suspension.appeals.proof', $appeal) }}" alt="Image attached to your appeal">
                            <span>View image attached to your appeal</span>
                        </a>
                    @endif
                    @if ($appeal->admin_note)
                        <p class="support-admin-note"><strong>{{ $appeal->status === 'denied' ? 'Support response:' : 'Support update:' }}</strong> {{ $appeal->admin_note }}</p>
                    @endif
                </article>
            @endif

            @if (! $appeal || ! in_array($appeal->status, ['pending', 'under_review'], true))
                <form method="POST" action="{{ route('account.suspension.appeals.store') }}" class="support-review-form" id="appeal">
                    @csrf
                    <h2 class="support-section-title">{{ $appeal?->status === 'denied' ? 'Send a counter-appeal' : 'Appeal this suspension' }}</h2>
                    <p class="support-intro">
                        @if ($appeal?->status === 'denied')
                            Reply to the support message above and explain what you would like us to reconsider. You can attach the missing image if you have it.
                        @else
                            Explain why you think the suspension should be reviewed. Our support team will review your message.
                        @endif
                    </p>
                    @if ($errors->any())
                        <div class="support-errors" role="alert">
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <label class="form-label" for="message">{{ $appeal?->status === 'denied' ? 'Your response to support' : 'Your message' }}</label>
                    <textarea class="form-control" id="message" name="message" rows="6" maxlength="5000" required>{{ old('message') }}</textarea>
                    <label class="form-label" for="proof">Attach an image <span class="optional-label">optional · JPG, PNG, or WebP · up to 5 MB</span></label>
                    <input class="form-control support-file-input" id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp">
                    <button class="button button-navy support-submit" type="submit">Send appeal</button>
                </form>
            @else
                <p class="support-hint" id="appeal">Your appeal is being reviewed. You can submit another appeal after this one has been resolved.</p>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="suspension-logout-form">
                @csrf
                <button class="button button-dark" type="submit">Log out</button>
            </form>
        </section>
    </div>
@endsection
