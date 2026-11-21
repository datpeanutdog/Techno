@extends('layouts.app')

@section('title', 'Contact customer support — PASABUY')

@section('content')
    <div class="support-shell">
        <a class="back-link" href="{{ route('marketplace.index') }}">← Back to the marketplace</a>
        <section class="support-card">
            <p class="eyebrow">CUSTOMER SERVICE</p>
            <h1>Contact customer support</h1>
            <p class="support-intro">You can contact us without logging in. Share an email address so our team can follow up after reviewing your message.</p>

            @if ($errors->any())
                <div class="support-errors" role="alert">
                    <p>Please review the following:</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('support.reports.contact.store') }}">
                @csrf
                <label class="form-label" for="contact_name">Your name</label>
                <input class="form-control" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" maxlength="60" autocomplete="name" required>

                <label class="form-label" for="contact_email">Email address where we can reach you</label>
                <input class="form-control" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}" maxlength="255" autocomplete="email" required>

                <label class="form-label" for="category">How can we help?</label>
                <select class="form-control" id="category" name="category" required>
                    <option value="">Choose a topic</option>
                    @foreach (\App\Models\SupportReport::CONTACT_CATEGORIES as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="details">Your message</label>
                <textarea class="form-control" id="details" name="details" maxlength="5000" rows="6" required>{{ old('details') }}</textarea>
                <p class="support-hint">For account recovery, include the email address associated with your account. Never include your password, reset code, or payment-card information.</p>

                <button class="button button-navy support-submit" type="submit">Send message to support</button>
            </form>

            <p class="auth-footnote">If you can access your account, you can also <a href="{{ route('login') }}">log in</a> to submit an account report.</p>
        </section>
    </div>
@endsection
