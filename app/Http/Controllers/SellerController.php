<?php

namespace App\Http\Controllers;

use App\Models\SellerReview;
use App\Models\User;
use App\Support\ListingCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $user->loadCount([
            'listings as active_listings_count' => fn ($query) => $query
                ->where('is_available', true)
                ->whereIn('category', ListingCategories::ALL),
            'receivedReviews',
        ])->loadAvg('receivedReviews', 'rating');

        return view('sellers.show', [
            'seller' => $user,
            'listings' => $user->listings()
                ->where('is_available', true)
                ->whereIn('category', ListingCategories::ALL)
                ->latest()
                ->paginate(8),
            'reviews' => $user->receivedReviews()
                ->with('reviewer:id,name,campus,avatar_path')
                ->latest()
                ->paginate(8, ['*'], 'reviews_page'),
            'myReview' => $request->user()?->writtenReviews()
                ->where('seller_id', $user->id)
                ->first(),
        ]);
    }

    public function review(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->id === $user->id, 403, 'You cannot review your own seller profile.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        SellerReview::query()->updateOrCreate(
            ['seller_id' => $user->id, 'reviewer_id' => $request->user()->id],
            $data,
        );

        return redirect()->route('sellers.show', $user)->with('status', 'Your seller review has been saved.');
    }
}
