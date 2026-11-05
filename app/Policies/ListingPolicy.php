<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function update(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id;
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id;
    }

    public function message(User $user, Listing $listing): bool
    {
        return $listing->is_available
            && $listing->user_id !== null
            && $listing->user_id !== $user->id;
    }
}
