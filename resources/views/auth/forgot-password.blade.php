@extends('layouts.app')

@section('title', 'Reset your password — PASABUY')

@section('content')
    <section class="auth-shell">
        <form class="auth-card" method="POST" action="{{ route('password.email') }}">
            @csrf
            <p class="eyebrow">Account recovery</p>
            <h1>Forgot your password?</h1>
            <p class="auth-intro">Enter your email and we’ll send a link to reset your password.</p>
            @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label class="form-label" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            <button class="button button-navy form-submit" type="submit">Send reset link <span aria-hidden="true">→</span></button>
            <p class="auth-footnote"><a href="{{ route('login') }}">Back to log in</a></p>
        </form>
    </section>
@endsection
