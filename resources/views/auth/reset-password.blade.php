@extends('layouts.app')

@section('title', 'Choose a new password — PASABUY')

@section('content')
    <section class="auth-shell">
        <form class="auth-card" method="POST" action="{{ route('password.update') }}">
            @csrf
            <p class="eyebrow">Account recovery</p>
            <h1>Choose a new password</h1>
            @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            <label class="form-label" for="password">New password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="button button-navy form-submit" type="submit">Reset password <span aria-hidden="true">→</span></button>
        </form>
    </section>
@endsection
