@extends('layouts.app')

@section('title', 'My support reports — Pasabuy')

@section('content')
    <div class="support-shell">
        <div class="support-heading-row">
            <div>
                <p class="eyebrow">CUSTOMER SERVICE</p>
                <h1>My support reports</h1>
                <p class="support-intro">Check the review status of reports you have submitted.</p>
            </div>
            <a class="button button-navy" href="{{ route('support.reports.create') }}">Submit a report</a>
        </div>

        @forelse ($reports as $report)
            <article class="support-report-card">
                <div class="support-report-topline">
                    <strong>{{ \App\Models\SupportReport::CATEGORIES[$report->category] ?? $report->category }}</strong>
                    <span class="support-status status-{{ $report->status }}">{{ ucfirst($report->status) }}</span>
                </div>
                <p class="support-report-target">Reported account: {{ $report->reportedUser?->name ?? 'Account no longer available' }}</p>
                <p class="support-report-details">{{ $report->details }}</p>
                @if ($report->admin_note)
                    <p class="support-admin-note"><strong>Support update:</strong> {{ $report->admin_note }}</p>
                @endif
                <div class="support-report-meta">
                    <span>{{ $report->created_at->format('M j, Y') }}</span>
                    <span>{{ count($report->proof_paths ?? []) }} proof image(s)</span>
                </div>
                @if ($report->proof_paths)
                    <div class="support-proof-grid">
                        @foreach ($report->proof_paths as $index => $proofPath)
                            <a href="{{ route('support.reports.proofs', ['supportReport' => $report, 'index' => $index]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('support.reports.proofs', ['supportReport' => $report, 'index' => $index]) }}" alt="Proof image {{ $index + 1 }}">
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="support-empty">
                <h2>No reports yet</h2>
                <p>Reports you submit to customer service will appear here.</p>
            </div>
        @endforelse

        <div class="pagination-wrap">{{ $reports->links() }}</div>
    </div>
@endsection
