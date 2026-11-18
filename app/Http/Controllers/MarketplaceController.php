<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Listing;
use App\Support\ListingCategories;
use App\Support\ListingData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:sale,rent'],
            'category' => ['nullable', 'in:'.implode(',', ListingCategories::ALL)],
            'sort' => ['nullable', 'in:latest,price_low,price_high'],
        ]);

        $query = Listing::query()
            ->with(['user' => fn ($user) => $user
                ->withCount('receivedReviews')
                ->withAvg('receivedReviews', 'rating')])
            ->where('is_available', true)
            ->whereIn('category', ListingCategories::ALL);

        if (! empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(function ($listings) use ($term): void {
                $listings->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            });
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        $query->where('type', $filters['mode'] ?? 'sale');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        match ($filters['sort'] ?? 'latest') {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default => $query->orderByDesc('featured')->latest(),
        };

        return view('marketplace.index', [
            'listings' => $query->paginate(8)->withQueryString(),
            'categories' => ListingCategories::ALL,
        ]);
    }

    public function show(Listing $listing): View
    {
        abort_unless($listing->is_available && in_array($listing->category, ListingCategories::ALL, true), 404);
        $listing->load(['user' => fn ($user) => $user
            ->withCount('receivedReviews')
            ->withAvg('receivedReviews', 'rating')]);

        $conversation = null;
        if (auth()->check() && $listing->user_id && $listing->user_id !== auth()->id()) {
            $conversation = Conversation::query()
                ->where('listing_id', $listing->id)
                ->where('buyer_id', auth()->id())
                ->first();
        }

        return view('marketplace.show', compact('listing', 'conversation'));
    }

    public function messageSeller(Listing $listing): RedirectResponse
    {
        Gate::authorize('message', $listing);

        return redirect()->to(route('listings.show', $listing).'#contact');
    }

    public function create(): View
    {
        return view('marketplace.create', ['categories' => ListingCategories::ALL]);
    }

    public function store(Request $request): RedirectResponse
    {
        $listing = $request->user()->listings()->create(ListingData::validated($request, $request->user()));

        return redirect()
            ->route('listings.show', $listing)
            ->with('status', 'Your listing is live. Thanks for being part of PASABUY!');
    }

    public function myListings(Request $request): View
    {
        return view('marketplace.mine', [
            'listings' => $request->user()->listings()->latest()->paginate(10),
        ]);
    }

    public function edit(Listing $listing): View
    {
        Gate::authorize('update', $listing);

        return view('marketplace.create', [
            'listing' => $listing,
            'categories' => ListingCategories::ALL,
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('update', $listing);
        $listing->update(ListingData::validated($request, $request->user(), $listing));

        return redirect()->route('listings.show', $listing)->with('status', 'Your listing has been updated.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        Gate::authorize('delete', $listing);
        $listing->delete();

        return redirect()->route('my-listings')->with('status', 'Your listing has been removed.');
    }
}
