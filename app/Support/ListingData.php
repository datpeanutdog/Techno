<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingData
{
    /**
     * @return array<string, mixed>
     */
    public static function validated(Request $request, User $user, ?Listing $listing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:90'],
            'description' => ['required', 'string', 'max:1500'],
            'price' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'type' => ['required', 'in:sale,rent'],
            'rent_period' => ['nullable', 'required_if:type,rent', 'in:day,week,month'],
            'category' => ['required', 'in:'.implode(',', ListingCategories::ALL)],
            'location' => ['required', 'string', 'max:100'],
            'image_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\//i'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'condition' => ['nullable', 'in:Brand new,Like new,Good,Fair'],
        ]);

        unset($data['image'], $data['remove_image']);

        if ($request->hasFile('image')) {
            if ($listing?->image_path) {
                Storage::disk('public')->delete($listing->image_path);
            }
            $data['image_path'] = $request->file('image')->store('listings', 'public');
        } elseif ($request->boolean('remove_image') && $listing?->image_path) {
            Storage::disk('public')->delete($listing->image_path);
            $data['image_path'] = null;
        }

        $data['seller_name'] = $user->name;
        $data['seller_initials'] = collect(explode(' ', $user->name))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $data;
    }
}