<?php

namespace Tests\Feature;

use App\Models\SellerReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_seller_profile_shows_their_active_listings_and_reviews(): void
    {
        $seller = User::factory()->create([
            'name' => 'Jamie Seller',
            'email' => 'seller@example.test',
            'campus' => 'Main Campus',
            'bio' => 'Engineering student',
        ]);
        $reviewer = User::factory()->create(['name' => 'Taylor Reviewer']);
        $seller->listings()->create([
            'title' => 'Calculus textbook',
            'description' => 'A useful class book.',
            'price' => 180,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main Campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'JS',
        ]);
        SellerReview::create([
            'seller_id' => $seller->id,
            'reviewer_id' => $reviewer->id,
            'rating' => 4,
            'body' => 'Friendly and easy to arrange a pickup with.',
        ]);

        $this->get(route('sellers.show', $seller))
            ->assertOk()
            ->assertSee('Jamie Seller')
            ->assertSee('Engineering student')
            ->assertSee('Calculus textbook')
            ->assertSee('Taylor Reviewer')
            ->assertSee('Friendly and easy to arrange a pickup with.')
            ->assertDontSee('seller@example.test');
    }

    public function test_signed_in_members_can_post_and_update_one_review_per_seller(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();

        $this->actingAs($reviewer)->post(route('sellers.reviews.store', $seller), [
            'rating' => 5,
            'body' => 'Great communication.',
        ])->assertRedirect(route('sellers.show', $seller));

        $this->actingAs($reviewer)->post(route('sellers.reviews.store', $seller), [
            'rating' => 3,
            'body' => 'Updated after meeting in person.',
        ])->assertRedirect(route('sellers.show', $seller));

        $this->assertDatabaseCount('seller_reviews', 1);
        $this->assertDatabaseHas('seller_reviews', [
            'seller_id' => $seller->id,
            'reviewer_id' => $reviewer->id,
            'rating' => 3,
            'body' => 'Updated after meeting in person.',
        ]);
    }

    public function test_reviews_require_a_signed_in_member_and_cannot_rate_yourself(): void
    {
        $seller = User::factory()->create();
        $payload = ['rating' => 5, 'body' => 'A helpful seller.'];

        $this->post(route('sellers.reviews.store', $seller), $payload)
            ->assertRedirect(route('login'));

        $this->actingAs($seller)
            ->post(route('sellers.reviews.store', $seller), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('seller_reviews', 0);
    }

    public function test_reviews_require_a_rating_between_one_and_five_and_a_comment(): void
    {
        $seller = User::factory()->create();
        $reviewer = User::factory()->create();

        $this->actingAs($reviewer)
            ->from(route('sellers.show', $seller))
            ->post(route('sellers.reviews.store', $seller), ['rating' => 6, 'body' => 'A comment.'])
            ->assertRedirect(route('sellers.show', $seller))
            ->assertSessionHasErrors('rating');

        $this->actingAs($reviewer)
            ->from(route('sellers.show', $seller))
            ->post(route('sellers.reviews.store', $seller), ['rating' => 5, 'body' => ''])
            ->assertRedirect(route('sellers.show', $seller))
            ->assertSessionHasErrors('body');
    }
}
