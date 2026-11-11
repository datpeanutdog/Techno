<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_school_listings_and_categories_for_the_selected_mode(): void
    {
        $this->seed();

        $response = $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/listings?mode=rent');

        $response->assertOk()
            ->assertJsonPath('data.0.type', 'rent')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonFragment(['Books & Notes']);
    }

    public function test_api_filters_school_listings_by_search_and_pickup_location(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/listings?mode=sale&q=engineering&location=Cagayan%20de%20Oro')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Engineering calculus book set');
    }

    public function test_api_does_not_expose_legacy_non_school_listings(): void
    {
        $listing = Listing::create([
            'title' => 'Legacy listing',
            'slug' => 'legacy-listing',
            'description' => 'A listing in an unsupported category.',
            'price' => 100,
            'type' => 'sale',
            'category' => 'Electronics',
            'location' => 'Campus',
            'seller_name' => 'Jamie Lee',
            'seller_initials' => 'JL',
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/listings?mode=sale')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/listings/'.$listing->slug)->assertNotFound();
    }

    public function test_api_creates_a_school_rental_listing(): void
    {
        $user = User::factory()->create(['name' => 'Jamie Lee']);
        $this->actingAs($user, 'sanctum')->postJson('/api/listings', [
            'title' => 'Calculator for an exam',
            'description' => 'Scientific calculator available by the hour.',
            'price' => 90,
            'type' => 'rent',
            'rent_period' => 'hour',
            'category' => 'Tech & Calculators',
            'location' => 'Campus library',
            'seller_name' => 'Untrusted seller name',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'rent')
            ->assertJsonPath('data.rent_period', 'hour');

        $this->assertDatabaseHas('listings', [
            'title' => 'Calculator for an exam',
            'seller_initials' => 'JL',
            'seller_name' => 'Jamie Lee',
            'user_id' => $user->id,
        ]);
    }

    public function test_api_seller_profiles_and_reviews_are_private_and_can_be_updated(): void
    {
        $seller = User::factory()->create(['email' => 'seller@example.test']);
        $reviewer = User::factory()->create();
        $seller->listings()->create([
            'title' => 'Seller listing',
            'description' => 'A school supply listing.',
            'price' => 80,
            'type' => 'sale',
            'category' => 'School Supplies',
            'location' => 'Main Campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'SS',
        ]);

        $this->getJson('/api/sellers/'.$seller->id)->assertUnauthorized();

        $this->actingAs($reviewer, 'sanctum')->getJson('/api/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.name', $seller->name)
            ->assertJsonPath('data.can_review', true)
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonMissingPath('data.email');
        $this->actingAs($reviewer, 'sanctum')->getJson('/api/listings?mode=sale')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', $seller->name)
            ->assertJsonMissingPath('data.0.user.email');

        $this->actingAs($reviewer, 'sanctum')->postJson('/api/sellers/'.$seller->id.'/reviews', [
            'rating' => 4,
            'body' => 'Easy to arrange a campus meetup.',
        ])->assertOk()->assertJsonPath('data.rating', 4);

        $this->actingAs($reviewer, 'sanctum')->postJson('/api/sellers/'.$seller->id.'/reviews', [
            'rating' => 5,
            'body' => 'Updated review.',
        ])->assertOk()->assertJsonPath('data.rating', 5);

        $this->assertDatabaseCount('seller_reviews', 1);
        $this->actingAs($reviewer, 'sanctum')->getJson('/api/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.average_rating', 5)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.my_review.body', 'Updated review.');

        $this->actingAs($seller, 'sanctum')->postJson('/api/sellers/'.$seller->id.'/reviews', [
            'rating' => 5,
            'body' => 'I like myself.',
        ])->assertForbidden();
    }

    public function test_api_rejects_non_school_categories_and_rentals_without_a_period(): void
    {
        $payload = [
            'title' => 'Lab coat for biology class',
            'description' => 'Clean lab coat, size medium.',
            'price' => 90,
            'type' => 'rent',
            'category' => 'Electronics',
            'location' => 'Science building',
            'seller_name' => 'Jamie Lee',
        ];

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/listings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rent_period', 'category']);
    }

    public function test_api_requires_sign_in_to_post(): void
    {
        $payload = [
            'title' => 'Lab coat for biology class',
            'description' => 'Clean lab coat, size medium.',
            'price' => 90,
            'type' => 'sale',
            'category' => 'Uniforms',
            'location' => 'Science building',
        ];

        $this->postJson('/api/listings', $payload)->assertUnauthorized();
        $this->getJson('/api/listings?mode=sale')->assertUnauthorized();
        $this->getJson('/api/listings/some-listing')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/listings', $payload)
            ->assertCreated();
    }

    public function test_mobile_email_verification_endpoint_is_not_enabled(): void
    {
        $this->postJson('/api/auth/verification-notification')->assertNotFound();
    }

    public function test_mobile_users_can_only_manage_their_own_listings(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $listing = $owner->listings()->create([
            'title' => 'My chemistry guide',
            'description' => 'A useful lab reference.',
            'price' => 150,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Campus',
            'seller_name' => $owner->name,
            'seller_initials' => 'OW',
        ]);

        $this->actingAs($otherUser, 'sanctum')->getJson('/api/my-listings')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($otherUser, 'sanctum')->putJson('/api/my-listings/'.$listing->id, [])
            ->assertNotFound();
        $this->assertDatabaseHas('listings', ['id' => $listing->id]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/my-listings')
            ->assertOk()->assertJsonPath('data.0.id', $listing->id);
        $this->actingAs($owner, 'sanctum')->deleteJson('/api/my-listings/'.$listing->id)
            ->assertOk();
        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
    }
}
