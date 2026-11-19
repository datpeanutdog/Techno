@extends('layouts.app')

@section('title', 'Support reports — PASABUY Admin')

@section('content')
    <div class="support-shell">
        <div class="support-heading-row">
            <div>
                <p class="eyebrow">ADMIN ACCOUNT PANEL</p>
                <h1>Support reports</h1>
                <p class="support-intro">Review account reports and customer support requests.</p>
            </div>
            <a class="button button-dark" href="{{ route('marketplace.index') }}">Back to marketplace</a>
        </div>

        @forelse ($reports as $report)
            <a class="admin-report-card" href="{{ route('admin.support-reports.show', $report) }}">
                <div class="support-report-topline">
                    <strong>{{ \App\Models\SupportReport::CATEGORIES[$report->category] ?? $report->category }}</strong>
                    <span class="support-status status-{{ $report->status }}">{{ ucfirst($report->status) }}</span>
                </div>
                <div class="admin-report-info">
                    <span>
                        <small>Reported account</small>
                        {{ $report->reportedUser?->name ?? (array_key_exists($report->category, \App\Models\SupportReport::CONTACT_CATEGORIES) ? 'Not applicable' : 'Account no longer available') }}
                    </span>
                    <span>
                        <small>Submitted by</small>
                        {{ $report->reporter?->name ?? $report->contact_name ?? 'Account no longer available' }}
                        @if ($report->contact_email)
                            <br>{{ $report->contact_email }}
                        @endif
                    </span>
                    <span><small>Evidence</small>{{ count($report->proof_paths ?? []) }} image(s)</span>
                    <span><small>Received</small>{{ $report->created_at->format('M j, Y · g:i A') }}</span>
                </div>
                <p class="support-report-details">{{ \Illuminate\Support\Str::limit($report->details, 220) }}</p>
                <span class="admin-report-open">Review report →</span>
            </a>
        @empty
            <div class="support-empty">
                <h2>No support reports</h2>
                <p>Submitted reports will appear here for review.</p>
            </div>
        @endforelse

        <div class="pagination-wrap">{{ $reports->links() }}</div>
    </div>
@endsection
