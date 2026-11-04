@extends('layouts.app')

@section('title', (isset($listing) ? 'Edit your listing' : 'Post a campus find').' — PASABUY')

@section('content')
    <div class="create-shell">
        <a class="back-link" href="{{ route('marketplace.index') }}">← Back to the marketplace</a>
        <div class="create-layout">
            <section class="create-intro">
                <p class="eyebrow"><span class="eyebrow-spark">✳</span> Make campus life easier</p>
                <h1>{{ isset($listing) ? 'Update your campus find.' : 'Pass a school find' }} @unless(isset($listing))<span>on.</span>@endunless</h1>
                <p>Got a textbook, uniform, calculator, or dorm essential to sell or rent? Another student could use it.</p>
                <div class="create-note"><span aria-hidden="true">☺</span><span>Keep listings focused on school, class, and campus life. Clear photos help students know what they're getting.</span></div>
                <div class="create-doodle" aria-hidden="true">✳</div>
            </section>

            <form class="listing-form" method="POST" action="{{ isset($listing) ? route('my-listings.update', $listing) : route('listings.store') }}">
                @csrf
                @if (isset($listing)) @method('PUT') @endif
                <div class="form-heading">
                    <span class="form-step">01</span>
                    <div><h2>{{ isset($listing) ? 'Edit your campus listing' : 'Post a campus listing' }}</h2><p>Share something another student can use.</p></div>
                </div>

                @if ($errors->any())
                    <div class="form-errors" role="alert">
                        <strong>A couple things need a second look:</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <label class="form-label" for="title">What school item or service are you sharing? <span>*</span></label>
                <input class="form-control" id="title" name="title" value="{{ old('title', $listing->title ?? '') }}" maxlength="90" placeholder="For example, calculus textbook or lab coat" required>

                <label class="form-label" for="description">Tell us a little more <span>*</span></label>
                <textarea class="form-control" id="description" name="description" rows="4" maxlength="1500" placeholder="Share the class, size, condition, or campus pickup details." required>{{ old('description', $listing->description ?? '') }}</textarea>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="price">Price in pesos <span>*</span></label>
                        <div class="price-input"><span>₱</span><input id="price" name="price" type="number" min="1" step="0.01" value="{{ old('price', $listing->price ?? '') }}" placeholder="0.00" required></div>
                    </div>
                    <div>
                        <label class="form-label" for="type">Sell or rent? <span>*</span></label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="sale" @selected(old('type', $listing->type ?? 'sale') === 'sale')>Sell it</option>
                            <option value="rent" @selected(old('type', $listing->type ?? 'sale') === 'rent')>Rent it out</option>
                        </select>
                    </div>
                </div>

                <div class="form-row rent-period-field" id="rent-period-field" @if (old('type', $listing->type ?? '') !== 'rent') hidden @endif>
                    <div>
                        <label class="form-label" for="rent_period">Rental price is per</label>
                        <select class="form-control" id="rent_period" name="rent_period">
                            <option value="day" @selected(old('rent_period', $listing->rent_period ?? 'day') === 'day')>Day</option>
                            <option value="week" @selected(old('rent_period', $listing->rent_period ?? '') === 'week')>Week</option>
                            <option value="month" @selected(old('rent_period', $listing->rent_period ?? '') === 'month')>Month</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="category">School category <span>*</span></label>
                        <select class="form-control" id="category" name="category" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(old('category', $listing->category ?? '') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="condition">How's it doing?</label>
                        <select class="form-control" id="condition" name="condition">
                            <option value="">Choose if you'd like</option>
                            @foreach (['Brand new', 'Like new', 'Good', 'Fair'] as $condition)
                                <option value="{{ $condition }}" @selected(old('condition', $listing->condition ?? '') === $condition)>{{ $condition }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="form-label" for="location">Campus or pickup area <span>*</span></label>
                <input class="form-control" id="location" name="location" value="{{ old('location', $listing->location ?? '') }}" maxlength="100" placeholder="For example, main campus or campus library" required>
                <p class="form-help">Posted by {{ auth()->user()->name }} from your PASABUY account.</p>

                <label class="form-label" for="image_url">A photo link <span class="optional-label">optional</span></label>
                <input class="form-control" id="image_url" name="image_url" type="url" value="{{ old('image_url', $listing->image_url ?? '') }}" maxlength="2048" placeholder="https://...">
                <p class="form-help">Add a link to a photo that starts with https://.</p>

                <button class="button button-navy form-submit" type="submit">{{ isset($listing) ? 'Save listing changes' : 'Post campus listing' }} <span aria-hidden="true">→</span></button>
                <p class="form-fine-print">Post school-related listings and arrange a safe campus handoff.</p>
            </form>
        </div>
    </div>
@endsection
