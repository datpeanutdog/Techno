@extends('layouts.app')

@section('title', 'Create your account — PASABUY')

@section('content')
    <section class="auth-shell">
        <form class="auth-card" method="POST" action="{{ route('register') }}">
            @csrf
            <p class="eyebrow">Join your campus marketplace</p>
            <h1>Create an account</h1>
            <p class="auth-intro">Sign up to post and manage your school-related listings.</p>
            @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label class="form-label" for="name">Display name <span>*</span></label>
            <input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="60" autocomplete="name" required>
            <label class="form-label" for="email">Email address <span>*</span></label>
            <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            <label class="form-label" for="password">Password <span>*</span></label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
            <label class="form-label" for="password_confirmation">Confirm password <span>*</span></label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="button button-navy form-submit" type="submit">Create account <span aria-hidden="true">→</span></button>
            <p class="auth-footnote">Already a member? <a href="{{ route('login') }}">Log in</a></p>
        </form>
    </section>
@endsection
