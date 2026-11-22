@extends('layouts.app')

@section('title', 'Review suspension appeal — Pasabuy Admin')

@section('content')
    <div class="support-shell">
        <a class="back-link" href="{{ route('admin.suspension-appeals.index') }}">← Back to appeals</a>
        <section class="support-card">
            <div class="support-report-topline">
                <div>
                    <p class="eyebrow">SUSPENSION APPEAL #{{ $appeal->id }}</p>
                    <h1>{{ $appeal->user?->name ?? 'Account no longer available' }}</h1>
                </div>
                <span class="support-status status-{{ $appeal->status }}">{{ str($appeal->status)->replace('_', ' ')->title() }}</span>
            </div>

            <div class="admin-report-info admin-appeal-info">
                <span><small>Account ID</small>{{ $appeal->user?->account_identifier }}</span>
                <span><small>Email</small>{{ $appeal->user?->email }}</span>
                <span><small>Received</small>{{ $appeal->created_at->format('M j, Y · g:i A') }}</span>
                <span><small>Current status</small>{{ $appeal->user?->isSuspended() ? 'Suspended' : 'Restored' }}</span>
            </div>

            <h2 class="support-section-title">Suspension reason</h2>
            <p class="support-report-details">{{ $appeal->user?->suspension_reason ?: 'No suspension reason is currently recorded.' }}</p>

            @if ($appeal->previousAppeal)
                <article class="suspension-appeal-status appeal-history-card">
                    <h2 class="support-section-title">Previous appeal #{{ $appeal->previousAppeal->id }}</h2>
                    <p class="support-report-details">{{ $appeal->previousAppeal->message }}</p>
                    <h3 class="support-section-title">Previous support response</h3>
                    <p class="support-admin-note">{{ $appeal->previousAppeal->admin_note ?: 'No response message was included.' }}</p>
                </article>
            @endif

            <h2 class="support-section-title">{{ $appeal->previousAppeal ? 'Counter-appeal message' : 'Appeal message' }}</h2>
            <p class="support-report-details">{{ $appeal->message }}</p>
            @if ($appeal->proof_path)
                <div class="appeal-evidence">
                    <h2 class="support-section-title">Image evidence</h2>
                    <a href="{{ route('admin.suspension-appeals.proof', $appeal) }}" target="_blank" rel="noopener">
                        <img class="appeal-proof-image" src="{{ route('admin.suspension-appeals.proof', $appeal) }}" alt="Image attached to the appeal">
                    </a>
                </div>
            @endif

            @if (in_array($appeal->status, ['pending', 'under_review'], true))
                <form method="POST" action="{{ route('admin.suspension-appeals.update', $appeal) }}" class="support-review-form">
                    @csrf
                    @method('PATCH')
                    <h2 class="support-section-title">Review decision</h2>
                    <label class="form-label" for="status">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        @foreach (['under_review' => 'Under review', 'approved' => 'Approve and restore account', 'denied' => 'Deny appeal'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $appeal->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="form-label" for="admin_note">Message to the account holder</label>
                    <textarea class="form-control" id="admin_note" name="admin_note" maxlength="2000" rows="4">{{ old('admin_note', $appeal->admin_note) }}</textarea>
                    <button class="button button-navy support-submit" type="submit">Save review</button>
                </form>
            @elseif ($appeal->admin_note)
                <p class="support-admin-note"><strong>Support update:</strong> {{ $appeal->admin_note }}</p>
            @endif
        </section>
    </div>
@endsection
