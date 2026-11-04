@extends('layouts.app')

@section('title', 'Your profile — PASABUY')

@section('content')
    @php
        $avatarUrl = auth()->user()->avatar_path
            ? (Illuminate\Support\Str::startsWith(auth()->user()->avatar_path, ['http://', 'https://'])
                ? auth()->user()->avatar_path
                : Illuminate\Support\Facades\Storage::url(auth()->user()->avatar_path))
            : null;
    @endphp
    <section class="auth-shell">
        <form class="auth-card profile-card" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <p class="eyebrow">Your campus identity</p>
            <h1>Profile settings</h1>
            <p class="auth-intro">Make it easier for other students to know who they’re buying or renting from.</p>
            @if ($errors->any())<div class="form-errors" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="profile-avatar-preview">
                @if ($avatarUrl)<img src="{{ $avatarUrl }}" alt="Your profile photo">@else<span>{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>@endif
            </div>
            <label class="form-label" for="name">Display name <span>*</span></label>
            <input class="form-control" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="60" required>
            <label class="form-label" for="campus">Campus</label>
            <input class="form-control" id="campus" name="campus" value="{{ old('campus', auth()->user()->campus) }}" maxlength="100" placeholder="Your school or campus">
            <label class="form-label" for="bio">Short bio</label>
            <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="500" placeholder="A little about you">{{ old('bio', auth()->user()->bio) }}</textarea>
            <label class="form-label" for="avatar">Upload a profile photo</label>
            <input class="form-control" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
            <p class="form-help">JPG, PNG, or WebP, up to 2 MB. You can instead provide a secure photo link below.</p>
            <label class="form-label" for="avatar_url">Or photo link</label>
            <input class="form-control" id="avatar_url" name="avatar_url" type="url" value="{{ old('avatar_url') }}" maxlength="2048" placeholder="https://...">
            <p class="form-help">Account email: {{ auth()->user()->email }} · <a href="{{ route('my-listings') }}">Manage your listings</a></p>
            <button class="button button-navy form-submit" type="submit">Save profile <span aria-hidden="true">→</span></button>
        </form>
    </section>
@endsection
