@extends('layouts.app')

@section('title', 'Manage users — PASABUY Admin')

@section('content')
    <div class="support-shell">
        <div class="support-heading-row">
            <div>
                <p class="eyebrow">ADMIN ACCOUNT PANEL</p>
                <h1>Manage users</h1>
                <p class="support-intro">Assign administrator access to trusted accounts.</p>
            </div>
            <a class="button button-dark" href="{{ route('admin.support-reports.index') }}">Back to admin</a>
        </div>

        @if ($errors->any())
            <div class="form-errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @forelse ($users as $user)
            <section class="admin-report-card">
                <div class="support-report-topline">
                    <strong>{{ $user->name }}</strong>
                    <span class="support-status">{{ ucfirst($user->role) }}</span>
                </div>
                <div class="admin-report-info">
                    <span><small>Email</small>{{ $user->email }}</span>
                    <span><small>Account</small>{{ $user->account_identifier }}</span>
                </div>

                @if (auth()->user()->is($user))
                    <p class="support-hint">You cannot change your own role.</p>
                @else
                    <form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="support-review-form">
                        @csrf
                        @method('PATCH')
                        <label class="form-label" for="role-{{ $user->id }}">Role</label>
                        <select class="form-control" id="role-{{ $user->id }}" name="role" required>
                            <option value="{{ \App\Models\User::ROLE_USER }}" @selected($user->role === \App\Models\User::ROLE_USER)>User</option>
                            <option value="{{ \App\Models\User::ROLE_ADMIN }}" @selected($user->role === \App\Models\User::ROLE_ADMIN)>Admin</option>
                        </select>
                        <button class="button button-navy support-submit" type="submit">Save role</button>
                    </form>
                @endif
            </section>
        @empty
            <div class="support-empty">
                <h2>No accounts found</h2>
                <p>Registered accounts will appear here.</p>
            </div>
        @endforelse

        <div class="pagination-wrap">{{ $users->links() }}</div>
    </div>
@endsection
