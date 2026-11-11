<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerReview;
use App\Models\User;
use App\Support\ListingCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        $user->loadCount([
            'listings as active_listings_count' => fn ($query) => $query
                ->where('is_available', true)
                ->whereIn('category', ListingCategories::ALL),
            'receivedReviews',
        ])->loadAvg('receivedReviews', 'rating');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'bio' => $user->bio,
            'campus' => $user->campus,
            'avatar_url' => $user->avatar_url,
            'can_review' => $request->user()->id !== $user->id,
            'average_rating' => $user->received_reviews_avg_rating === null
                ? null
                : (float) $user->received_reviews_avg_rating,
            'reviews_count' => $user->received_reviews_count,
            'listings' => $user->listings()
                ->where('is_available', true)
                ->whereIn('category', ListingCategories::ALL)
                ->latest()
                ->get(),
            'reviews' => $user->receivedReviews()
                ->with('reviewer:id,name,campus,avatar_path')
                ->latest()
                ->get()
                ->map(fn (SellerReview $review): array => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'body' => $review->body,
                    'created_at' => $review->created_at,
                    'reviewer' => [
                        'id' => $review->reviewer->id,
                        'name' => $review->reviewer->name,
                        'campus' => $review->reviewer->campus,
                        'avatar_url' => $review->reviewer->avatar_url,
                    ],
                ]),
            'my_review' => $request->user()->writtenReviews()
                ->where('seller_id', $user->id)
                ->first(['id', 'rating', 'body']),
        ]]);
    }

    public function review(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 403, 'You cannot review your own seller profile.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $review = SellerReview::query()->updateOrCreate(
            ['seller_id' => $user->id, 'reviewer_id' => $request->user()->id],
            $data,
        );

        return response()->json(['data' => [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'created_at' => $review->created_at,
        ]]);
    }
}
