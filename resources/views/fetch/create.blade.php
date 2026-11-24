@extends('layouts.app')

@section('title', 'Post a fetch request — Pasabuy')

@section('content')
    <section class="fetch-shell fetch-create-shell">
        <a class="back-link" href="{{ route('fetch.index') }}">← Back to live requests</a>

        <header class="fetch-heading fetch-create-heading">
            <p class="eyebrow">Campus request board</p>
            <h1>Post a fetch request</h1>
            <p>Tell students what you need and where to deliver it.</p>
        </header>

        <form class="listing-form fetch-form" method="POST" action="{{ route('fetch.requests.store') }}">
            @csrf

            @if ($errors->any())
                <div class="form-errors" role="alert">
                    <strong>Please check the details below.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <label class="form-label" for="item_name">Item you need</label>
            <input class="form-control" id="item_name" name="item_name" type="text" value="{{ old('item_name') }}" maxlength="90" placeholder="e.g. Bluebook and black pens" required>

            <label class="form-label" for="details">Details</label>
            <textarea class="form-control" id="details" name="details" maxlength="1000" placeholder="Add helpful details like size, quantity, or preferred brand." required>{{ old('details') }}</textarea>

            <div class="form-row">
                <div>
                    <label class="form-label" for="budget">Budget <span class="optional-label">optional</span></label>
                    <input class="form-control" id="budget" name="budget" type="number" value="{{ old('budget') }}" min="0" max="9999999999.99" step="0.01" placeholder="₱">
                </div>
                <div>
                    <label class="form-label" for="delivery_location">Campus delivery spot</label>
                    <input class="form-control" id="delivery_location" name="delivery_location" type="text" value="{{ old('delivery_location') }}" maxlength="100" placeholder="e.g. Main library entrance" required>
                </div>
            </div>
            <p class="form-help">For your privacy, use a campus pickup point rather than your home address.</p>

            <label class="form-label" for="duration_value">How long should it stay live? (required)</label>
            <div class="fetch-duration-control" data-fetch-duration="live">
                <input
                    class="form-control"
                    id="duration_value"
                    name="duration_value"
                    type="number"
                    value="{{ old('duration_value', 30) }}"
                    min="1"
                    max="1440"
                    step="1"
                    required
                    aria-describedby="fetch-duration-help"
                >
                <select class="form-control" id="duration_unit" name="duration_unit" required aria-label="Duration unit">
                    <option value="minutes" @selected(old('duration_unit', 'minutes') === 'minutes')>Minutes</option>
                    <option value="hours" @selected(old('duration_unit') === 'hours')>Hours</option>
                    <option value="days" @selected(old('duration_unit') === 'days')>Days</option>
                </select>
            </div>
            <p class="form-help" id="fetch-duration-help" data-fetch-duration-help>Choose 5 to 1440 minutes, 1 to 24 hours, or 1 day.</p>

            <label class="form-label" for="delivery_duration_value">Delivery deadline after posting (required)</label>
            <div class="fetch-duration-control" data-fetch-duration="delivery">
                <input
                    class="form-control"
                    id="delivery_duration_value"
                    name="delivery_duration_value"
                    type="number"
                    value="{{ old('delivery_duration_value', 2) }}"
                    min="1"
                    max="720"
                    step="1"
                    required
                    aria-describedby="fetch-delivery-duration-help"
                >
                <select class="form-control" id="delivery_duration_unit" name="delivery_duration_unit" required aria-label="Delivery deadline unit">
                    <option value="minutes" @selected(old('delivery_duration_unit') === 'minutes')>Minutes</option>
                    <option value="hours" @selected(old('delivery_duration_unit', 'hours') === 'hours')>Hours</option>
                    <option value="days" @selected(old('delivery_duration_unit') === 'days')>Days</option>
                </select>
            </div>
            <p class="form-help" id="fetch-delivery-duration-help" data-fetch-duration-help>Choose 5 minutes to 30 days, later than the live listing expiration.</p>

            <button class="button button-navy form-submit" type="submit">Post request</button>
        </form>
    </section>
@endsection
