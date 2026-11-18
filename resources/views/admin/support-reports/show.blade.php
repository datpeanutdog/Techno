@extends('layouts.app')

@section('title', 'Review support report — PASABUY Admin')

@section('content')
    <div class="support-shell">
        <a class="back-link" href="{{ route('admin.support-reports.index') }}">← Back to reports</a>
        <section class="support-card">
            <div class="support-report-topline">
                <div>
                    <p class="eyebrow">REPORT #{{ $report->id }}</p>
                    <h1>{{ \App\Models\SupportReport::CATEGORIES[$report->category] ?? $report->category }}</h1>
                </div>
                <span class="support-status status-{{ $report->status }}">{{ ucfirst($report->status) }}</span>
            </div>

            <div class="admin-report-info">
                <span><small>Reported account</small>{{ $report->reportedUser?->name ?? 'Account no longer available' }}<br>{{ $report->reportedUser?->account_identifier }}<br>{{ $report->reportedUser?->email }}</span>
                <span><small>Submitted by</small>{{ $report->reporter?->name ?? 'Account no longer available' }}<br>{{ $report->reporter?->account_identifier }}<br>{{ $report->reporter?->email }}</span>
                <span><small>Received</small>{{ $report->created_at->format('M j, Y · g:i A') }}</span>
            </div>

            <h2 class="support-section-title">Report details</h2>
            <p class="support-report-details">{{ $report->details }}</p>

            <h2 class="support-section-title">Proof images</h2>
            @if ($report->proof_paths)
                <div class="support-proof-grid support-proof-grid-admin">
                    @foreach ($report->proof_paths as $index => $proofPath)
                        <a href="{{ route('support.reports.proofs', ['supportReport' => $report, 'index' => $index]) }}" target="_blank" rel="noopener">
                            <img src="{{ route('support.reports.proofs', ['supportReport' => $report, 'index' => $index]) }}" alt="Proof image {{ $index + 1 }}">
                        </a>
                    @endforeach
                </div>
            @else
                <p class="support-hint">No images were attached to this report.</p>
            @endif

            @if ($report->reportedUser?->isSuspended())
                <div class="support-suspension-note">
                    <strong>Account suspended</strong>
                    <p>{{ $report->reportedUser->suspension_reason }}</p>
                    <form method="POST" action="{{ route('admin.support-reports.restore', $report) }}">
                        @csrf
                        <button class="button button-dark" type="submit">Restore account</button>
                    </form>
                </div>
            @elseif ($report->reportedUser)
                <form method="POST" action="{{ route('admin.support-reports.suspend', $report) }}" class="support-suspend-form">
                    @csrf
                    <p>Suspending this account blocks web actions and revokes its mobile API tokens. Review the report and evidence before continuing.</p>
                    <button class="button button-danger" type="submit">Suspend reported account</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.support-reports.update', $report) }}" class="support-review-form">
                @csrf
                @method('PATCH')
                <h2 class="support-section-title">Review outcome</h2>
                <label class="form-label" for="status">Status</label>
                <select class="form-control" id="status" name="status" required>
                    @foreach (['reviewed' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $value => $label)
                        <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="form-label" for="admin_note">Note for the reporter</label>
                <textarea class="form-control" id="admin_note" name="admin_note" maxlength="2000" rows="3">{{ old('admin_note', $report->admin_note) }}</textarea>
                <button class="button button-navy support-submit" type="submit">Save review</button>
            </form>
        </section>
    </div>
@endsection
