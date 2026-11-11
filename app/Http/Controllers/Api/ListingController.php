<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\ListingCategories;
use App\Support\ListingData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:sale,rent'],
            'category' => ['nullable', 'in:'.implode(',', ListingCategories::ALL)],
            'sort' => ['nullable', 'in:latest,price_low,price_high'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Listing::query()
            ->with(['user' => fn ($user) => $user
                ->select(['id', 'name', 'bio', 'campus', 'avatar_path'])
                ->withCount('receivedReviews')
                ->withAvg('receivedReviews', 'rating')])
            ->where('is_available', true)
            ->whereIn('category', ListingCategories::ALL)
            ->where('type', $filters['mode'] ?? 'sale');

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

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        match ($filters['sort'] ?? 'latest') {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default => $query->orderByDesc('featured')->latest(),
        };

        $listings = $query->paginate(20);

        return response()->json([
            'data' => $listings->items(),
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'total' => $listings->total(),
            ],
            'categories' => ListingCategories::ALL,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $listing = Listing::query()
            ->with(['user' => fn ($user) => $user
                ->select(['id', 'name', 'bio', 'campus', 'avatar_path'])
                ->withCount('receivedReviews')
                ->withAvg('receivedReviews', 'rating')])
            ->where('slug', $slug)
            ->where('is_available', true)
            ->whereIn('category', ListingCategories::ALL)
            ->firstOrFail();

        return response()->json(['data' => $listing]);
    }

    public function store(Request $request): JsonResponse
    {
        $listing = $request->user()->listings()->create(ListingData::validated($request, $request->user()));

        return response()->json(['data' => $listing], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->listings()->latest()->get()]);
    }

    public function owned(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $request->user()->listings()->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $listing = $request->user()->listings()->findOrFail($id);
        $listing->update(ListingData::validated($request, $request->user(), $listing));

        return response()->json(['data' => $listing->refresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->listings()->findOrFail($id)->delete();

        return response()->json(['message' => 'Your listing has been removed.']);
    }
}
