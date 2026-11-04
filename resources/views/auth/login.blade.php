@extends('layouts.app')

@section('title', 'Log in — PASABUY')

@section('content')
    <section class="auth-shell">
        <form class="auth-card" method="POST" action="{{ route('login') }}">
            @csrf
            <p class="eyebrow">Good finds, shared by students</p>
            <h1>Welcome back</h1>
            <p class="auth-intro">Log in to list, update, and manage your campus finds.</p>
            @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label class="form-label" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
            <label class="remember-label"><input type="checkbox" name="remember"> Keep me signed in</label>
            <button class="button button-navy form-submit" type="submit">Log in <span aria-hidden="true">→</span></button>
            <p class="auth-footnote"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
            <p class="auth-footnote">New to PASABUY? <a href="{{ route('register') }}">Create an account</a></p>
        </form>
    </section>
@endsection
