<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_seeded_marketplace_listings(): void
    {
        $this->seed();

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk()
            ->assertSee('School days,')
            ->assertSee('Engineering calculus book set')
            ->assertSee('Laptop for classes & projects')
            ->assertDontSee('Student lab coat')
            ->assertSee('<nav class="market-tabs" aria-label="Browse sale and rental listings">', false)
            ->assertSee('class="tab-active"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('For sale')
            ->assertSeeInOrder(['class="is-active"', 'aria-current="page"', '>Buy</a>'], false);
    }

    public function test_sell_navigation_opens_listing_form_and_marks_sell_as_active(): void
    {
        $this->actingAs(User::factory()->create())->get(route('listings.create'))
            ->assertOk()
            ->assertSee('Post campus listing')
            ->assertSeeInOrder(['class="is-active"', 'aria-current="page"', '>Sell</a>'], false);
    }

    public function test_search_filters_listings_by_keyword_and_location(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->create())
            ->get('/?q=engineering&location=Cagayan%20de%20Oro')
            ->assertOk()
            ->assertSee('Engineering calculus book set')
            ->assertDontSee('Student laptop');
    }

    public function test_rent_filter_only_shows_rentable_listings(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->create())->get('/?mode=rent')
            ->assertOk()
            ->assertSee('Scientific calculator for exams')
            ->assertSee('class="tab-active"', false)
            ->assertSee('For rent')
            ->assertDontSee('Engineering calculus book set');
    }

    public function test_listing_detail_page_is_available(): void
    {
        $this->seed();
        $listing = Listing::where('slug', 'engineering-calculus-books')->firstOrFail();

        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Mika Santos')
            ->assertSee('About this campus find');
    }

    public function test_non_school_categories_are_not_shown_in_the_marketplace(): void
    {
        $listing = Listing::create([
            'title' => 'Old generic listing',
            'slug' => 'old-generic-listing',
            'description' => 'A legacy listing from before the school marketplace.',
            'price' => 100,
            'type' => 'sale',
            'category' => 'Electronics',
            'location' => 'Main campus',
            'seller_name' => 'Jamie Lee',
            'seller_initials' => 'JL',
        ]);

        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()
            ->assertDontSee('Old generic listing');

        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))
            ->assertNotFound();
    }

    public function test_signed_in_user_can_create_a_sale_listing(): void
    {
        $user = User::factory()->create(['name' => 'Jamie Lee']);
        $response = $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'A lovely old engineering text',
            'description' => 'Works great and includes useful class notes.',
            'price' => 1200,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => 'Untrusted seller name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('listings', [
            'title' => 'A lovely old engineering text',
            'type' => 'sale',
            'location' => 'Main campus',
            'seller_initials' => 'JL',
            'seller_name' => 'Jamie Lee',
            'user_id' => $user->id,
        ]);
    }

    public function test_signed_in_user_can_create_a_rental_listing_with_a_period(): void
    {
        $response = $this->actingAs(User::factory()->create(['name' => 'Jamie Lee']))->post(route('listings.store'), [
            'title' => 'A lab coat ready to rent',
            'description' => 'A clean lab coat for science class.',
            'price' => 90,
            'type' => 'rent',
            'rent_period' => 'week',
            'category' => 'Uniforms',
            'location' => 'Science building',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('listings', [
            'title' => 'A lab coat ready to rent',
            'type' => 'rent',
            'rent_period' => 'week',
        ]);
    }

    public function test_rental_listing_requires_a_rental_period(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('listings.create'))->post(route('listings.store'), [
                'title' => 'A lab coat ready to rent',
                'description' => 'A clean lab coat for science class.',
                'price' => 90,
                'type' => 'rent',
                'category' => 'Uniforms',
                'location' => 'Science building',
                'seller_name' => 'Jamie Lee',
            ]);

        $response->assertRedirect(route('listings.create'))
            ->assertSessionHasErrors('rent_period');
    }

    public function test_visitors_must_sign_in_to_post_listings(): void
    {
        $this->get(route('listings.create'))->assertRedirect(route('login'));
        $this->post(route('listings.store'), [])->assertRedirect(route('login'));
    }

    public function test_marketplace_is_the_first_page_after_login_and_not_accessible_to_guests(): void
    {
        $user = User::factory()->create();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/?mode=rent')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/')->assertOk()->assertSee('School days,');
        $this->post('/logout')->assertRedirect(route('marketplace.index'));
    }

    public function test_login_and_registration_panels_only_show_the_brand_header(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('PASABUY home')
            ->assertSee('Welcome back')
            ->assertSee('Create an account')
            ->assertDontSee('Main navigation')
            ->assertDontSee('List an item');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create an account')
            ->assertSee('Log in')
            ->assertDontSee('Main navigation')
            ->assertDontSee('List an item');
    }

    public function test_signed_in_users_can_post_without_email_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('listings.create'))->assertOk();
        $this->actingAs($user)->post(route('listings.store'), [
            'title' => 'My lab coat',
            'description' => 'Clean lab coat for science class.',
            'price' => 90,
            'type' => 'sale',
            'category' => 'Uniforms',
            'location' => 'Science building',
        ])->assertRedirect();
    }

    public function test_email_verification_routes_are_not_enabled(): void
    {
        $this->get('/email/verify')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }

    public function test_users_cannot_edit_or_remove_other_users_listings(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $listing = $owner->listings()->create([
            'title' => 'Owned textbook',
            'description' => 'Used for one semester.',
            'price' => 100,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $owner->name,
            'seller_initials' => 'OW',
        ]);

        $this->actingAs($otherUser)->get(route('my-listings.edit', $listing))->assertForbidden();
        $this->actingAs($otherUser)->delete(route('my-listings.destroy', $listing))->assertForbidden();
        $this->assertDatabaseHas('listings', ['id' => $listing->id]);
    }

    public function test_owner_can_edit_and_remove_a_listing(): void
    {
        $owner = User::factory()->create(['name' => 'Jamie Lee']);
        $listing = $owner->listings()->create([
            'title' => 'Owned textbook',
            'description' => 'Used for one semester.',
            'price' => 100,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $owner->name,
            'seller_initials' => 'JL',
        ]);

        $this->actingAs($owner)->put(route('my-listings.update', $listing), [
            'title' => 'Updated textbook',
            'description' => 'Includes notes for the course.',
            'price' => 125,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Library entrance',
        ])->assertRedirect(route('listings.show', $listing->fresh()));

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'title' => 'Updated textbook',
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->delete(route('my-listings.destroy', $listing->fresh()))
            ->assertRedirect(route('my-listings'));
        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
    }
}
