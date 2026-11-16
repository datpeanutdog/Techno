@extends('layouts.app')

@section('title', 'Suspension appeals — PASABUY Admin')

@section('content')
    <div class="support-shell">
        <div class="support-heading-row">
            <div>
                <p class="eyebrow">ADMIN ACCOUNT PANEL</p>
                <h1>{{ $tab === 'active' ? 'Active appeals' : 'Resolved appeals' }}</h1>
                <p class="support-intro">{{ $tab === 'active' ? 'Review appeals that are waiting for a decision.' : 'Review appeals that have been approved or denied.' }}</p>
            </div>
        </div>

        <nav class="support-tabs" aria-label="Admin support">
            <a href="{{ route('admin.suspension-appeals.index', ['tab' => 'active']) }}" @if ($tab === 'active') aria-current="page" @endif>Active appeals</a>
            <a href="{{ route('admin.suspension-appeals.index', ['tab' => 'resolved']) }}" @if ($tab === 'resolved') aria-current="page" @endif>Resolved appeals</a>
        </nav>

        @forelse ($appeals as $appeal)
            <a class="admin-report-card" href="{{ route('admin.suspension-appeals.show', $appeal) }}">
                <div class="support-report-topline">
                    <strong>{{ $appeal->user?->name ?? 'Account no longer available' }} · {{ $appeal->user?->account_identifier }}</strong>
                    <span class="support-status status-{{ $appeal->status }}">{{ str($appeal->status)->replace('_', ' ')->title() }}</span>
                </div>
                <div class="admin-report-info admin-appeal-info">
                    <span><small>Email</small>{{ $appeal->user?->email }}</span>
                    <span><small>Received</small>{{ $appeal->created_at->format('M j, Y · g:i A') }}</span>
                    <span><small>Suspension status</small>{{ $appeal->user?->isSuspended() ? 'Suspended' : 'Restored' }}</span>
                </div>
                <p class="support-report-details">{{ \Illuminate\Support\Str::limit($appeal->message, 220) }}</p>
                <span class="admin-report-open">Review appeal →</span>
            </a>
        @empty
            <div class="support-empty">
                <h2>No {{ $tab }} appeals</h2>
                <p>{{ $tab === 'active' ? 'New appeals waiting for review will appear here.' : 'Approved and denied appeals will appear here.' }}</p>
            </div>
        @endforelse

        <div class="pagination-wrap">{{ $appeals->links() }}</div>
    </div>
@endsection
