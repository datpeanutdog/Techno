@extends('layouts.app')

@section('title', 'Contact customer support — Pasabuy')

@section('content')
    <div class="support-shell">
        <section class="support-card">
            <p class="eyebrow">CUSTOMER SERVICE</p>
            <h1>Report an account</h1>
            <p class="support-intro">Tell our support team what happened. Reports are reviewed by a Pasabuy admin, and a report does not automatically suspend an account.</p>

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

            <form method="POST" action="{{ route('support.reports.store') }}" enctype="multipart/form-data" data-support-report-form>
                @csrf
                @if ($reportedUser)
                    <input type="hidden" name="reported_user_id" value="{{ $reportedUser->id }}">
                    <p class="support-target-summary">Reporting account: <strong>{{ $reportedUser->name }}</strong></p>
                @else
                    <label class="form-label" for="reported_email">Reported account email</label>
                    <input class="form-control" id="reported_email" name="reported_email" type="email" value="{{ old('reported_email') }}" autocomplete="off" required>
                @endif

                <label class="form-label" for="category">What are you reporting?</label>
                <select class="form-control" id="category" name="category" required>
                    <option value="">Choose a report type</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="details">What happened?</label>
                <textarea class="form-control" id="details" name="details" maxlength="5000" rows="6" required>{{ old('details') }}</textarea>
                <p class="support-hint">Include relevant dates, messages, or transaction details. Do not include passwords or payment-card information.</p>

                <label class="form-label" for="proofs">Image proof <span class="optional-label">optional · up to 3 images, 4 MB each</span></label>
                <input class="form-control support-file-input" id="proofs" name="proofs[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                <div class="support-errors" role="alert" data-proof-size-error hidden></div>

                <button class="button button-navy support-submit" type="submit">Send report to support</button>
                <a class="support-history-link" href="{{ route('support.reports.mine') }}">View my submitted reports</a>
            </form>
        </section>
    </div>
@endsection
