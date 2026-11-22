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
            ->assertDontSee('School days,')
            ->assertSee('Search campus listings')
            ->assertSee('Filter by category')
            ->assertSee('All categories')
            ->assertSee('Search')
            ->assertSee('Engineering calculus book set')
            ->assertSee('Listed ', false)
            ->assertSee('data-save-url=', false)
            ->assertSee('class="listing-condition condition-good">Good</span>', false)
            ->assertSee('M6.75 3.75h10.5', false)
            ->assertSee('Laptop for classes & projects')
            ->assertDontSee('Student lab coat')
            ->assertSee('<nav class="market-tabs" aria-label="Browse buy and rent listings">', false)
            ->assertSee('market-mode-tab tab-active', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('>Buy<', false)
            ->assertSee('Find something to keep')
            ->assertSee('Borrow it for a while')
            ->assertSee('List an item')
            ->assertSee('<a class="button button-navy listings-create-button" href="'.route('listings.create').'">', false)
            ->assertDontSee('class="button button-navy header-sell"', false)
            ->assertDontSee('market-sell-link', false)
            ->assertDontSee('Good finds, shared by students like you.')
            ->assertDontSee('Buy · sell · rent · pass it on')
            ->assertSeeInOrder(['class="is-active"', 'aria-current="page"', '>Buy</span>'], false);
    }

    public function test_sidebar_shows_profile_and_spaced_navigation_in_order(): void
    {
        $this->actingAs(User::factory()->create())->get(route('listings.create'))
            ->assertOk()
            ->assertSee('Post campus listing')
            ->assertDontSee('Make campus life easier')
            ->assertDontSee('Pass a school find')
            ->assertSee('<option value="hour"', false)
            ->assertSee('List an item')
            ->assertDontSee('>Sell</span>', false)
            ->assertSee('<a class="sidebar-profile-link" href="'.route('profile.edit').'"', false)
            ->assertSee('class="sidebar-profile-name"', false)
            ->assertSeeInOrder([
                'class="sidebar-profile-link"',
                'class="sidebar-profile-avatar sidebar-profile-initial"',
                'class="sidebar-profile-name"',
                '>Buy</span>',
                '>Rent</span>',
                '>Save for later</span>',
                'Your account',
                '>Manage Listing</span>',
                'href="'.route('settings.index').'"',
                '>Settings</span>',
                '>Messages</span>',
                '>Support</span>',
            ], false)
            ->assertDontSee('<p class="sidebar-section-label">Marketplace</p>', false);
    }

    public function test_header_listing_action_is_hidden_on_account_pages(): void
    {
        $user = User::factory()->create();

        foreach ([
            route('conversations.index'),
            route('support.reports.mine'),
            route('my-listings'),
            route('profile.edit'),
            route('settings.index'),
        ] as $page) {
            $this->actingAs($user)->get($page)
                ->assertOk()
                ->assertSee('Save for later')
                ->assertSee('data-saved-link', false)
                ->assertDontSee('class="button button-navy header-sell"', false)
                ->assertDontSee('Good finds, shared by students like you.')
                ->assertDontSee('Buy · sell · rent · pass it on');
        }
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
            ->assertSee('market-mode-tab tab-active', false)
            ->assertSee('>Rent<', false)
            ->assertDontSee('Engineering calculus book set');
    }

    public function test_saved_marketplace_view_shows_only_the_current_users_saved_listings(): void
    {
        $seller = User::factory()->create();
        $savedSale = $seller->listings()->create([
            'title' => 'Saved sale item',
            'description' => 'A sale listing saved by this account.',
            'price' => 100,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'SS',
        ]);
        $savedRental = $seller->listings()->create([
            'title' => 'Saved rental item',
            'description' => 'A rental listing saved by this account.',
            'price' => 20,
            'type' => 'rent',
            'rent_period' => 'day',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'SS',
        ]);
        $unsaved = $seller->listings()->create([
            'title' => 'Unsaved marketplace item',
            'description' => 'This listing was not saved.',
            'price' => 50,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'SS',
        ]);

        $user = User::factory()->create();
        $user->savedListings()->attach([$savedSale->id, $savedRental->id]);

        $this->actingAs($user)
            ->get('/?view=saved')
            ->assertOk()
            ->assertSee('<h1>Save for later</h1>', false)
            ->assertSee('2 listings saved to your account.')
            ->assertSee('data-saved-view', false)
            ->assertSee('data-saved-link', false)
            ->assertDontSee('Search campus listings')
            ->assertDontSee('List an item')
            ->assertDontSee('Browse buy and rent listings')
            ->assertDontSee('Showing 2 campus finds')
            ->assertSee('Saved sale item')
            ->assertSee('Saved rental item')
            ->assertDontSee('Unsaved marketplace item');
    }

    public function test_saved_marketplace_view_has_a_clean_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/?view=saved')
            ->assertOk()
            ->assertSee('0 listings saved to your account.')
            ->assertSee('No saved items yet.')
            ->assertSee('Bookmark a listing and it will be waiting here.')
            ->assertSee('Browse campus finds')
            ->assertDontSee('Search campus listings')
            ->assertDontSee('List an item');
    }

    public function test_saved_listing_actions_persist_for_the_signed_in_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $listing = Listing::create([
            'title' => 'A listing worth saving',
            'slug' => 'a-listing-worth-saving',
            'description' => 'A saved listing for the marketplace.',
            'price' => 80,
            'type' => 'sale',
            'category' => 'School Supplies',
            'location' => 'Main campus',
            'seller_name' => 'Seller',
            'seller_initials' => 'SE',
        ]);

        $this->actingAs($user)->postJson(route('saved-listings.store', $listing))
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('listing_id', $listing->id);
        $this->actingAs($user)->postJson(route('saved-listings.store', $listing))->assertOk();
        $this->assertDatabaseCount('saved_listings', 1);

        $this->actingAs($user)->get('/')->assertSee('aria-pressed="true"', false);
        $this->actingAs($user)->get('/?view=saved')->assertSee('A listing worth saving');
        $this->actingAs($otherUser)->get('/?view=saved')->assertDontSee('A listing worth saving');

        $this->actingAs($user)->deleteJson(route('saved-listings.destroy', $listing))
            ->assertOk()
            ->assertJsonPath('saved', false);
        $this->assertDatabaseCount('saved_listings', 0);
    }

    public function test_settings_page_shows_theme_choices_and_account_tools(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Appearance')
            ->assertSee('name="theme"', false)
            ->assertSee('id="theme-system" type="radio" name="theme" value="system" data-theme-option checked', false)
            ->assertSee('value="system"', false)
            ->assertSee('value="light"', false)
            ->assertSee('value="dark"', false)
            ->assertSee(route('profile.edit'), false)
            ->assertSee(route('support.reports.create'), false);
    }

    public function test_listing_detail_page_is_available(): void
    {
        $this->seed();
        $listing = Listing::where('slug', 'engineering-calculus-books')->firstOrFail();

        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Mika Santos')
            ->assertSee('class="listing-condition condition-good">Good</span>', false)
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
            'title' => 'A calculator ready to rent',
            'description' => 'A scientific calculator for an afternoon exam.',
            'price' => 90,
            'type' => 'rent',
            'rent_period' => 'hour',
            'category' => 'Tech & Calculators',
            'location' => 'Campus library',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('listings', [
            'title' => 'A calculator ready to rent',
            'type' => 'rent',
            'rent_period' => 'hour',
        ]);
        $this->actingAs(User::factory()->create())
            ->get(route('listings.show', Listing::where('title', 'A calculator ready to rent')->firstOrFail()))
            ->assertOk()
            ->assertSee('/ hour');
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

    public function test_visitors_see_the_welcome_page_and_sign_in_is_required_for_browsing(): void
    {
        $this->seed();
        $user = User::factory()->create();

        $this->get('/')->assertOk()
            ->assertSee('Create your account')
            ->assertDontSee('Search campus listings')
            ->assertDontSee('Engineering calculus book set');
        $this->get('/?mode=rent')->assertOk()->assertDontSee('Scientific calculator for exams');
        $this->get(route('listings.show', Listing::where('slug', 'engineering-calculus-books')->firstOrFail()))
            ->assertRedirect(route('login'));
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Search campus listings');
        $this->post('/logout')->assertRedirect(route('marketplace.index'));
    }

    public function test_visitors_must_sign_in_to_view_listing_and_seller_details(): void
    {
        $seller = User::factory()->create();
        $listing = $seller->listings()->create([
            'title' => 'Used biology textbook',
            'description' => 'A helpful class reference.',
            'price' => 250,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'ST',
        ]);

        $this->get(route('listings.show', $listing))->assertRedirect(route('login'));
        $this->get(route('sellers.show', $seller))->assertRedirect(route('login'));
        $this->get(route('listings.messages.create', $listing))->assertRedirect(route('login'));
    }

    public function test_signing_in_from_a_listing_message_prompt_returns_to_the_contact_form(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $seller->listings()->create([
            'title' => 'Used biology textbook',
            'description' => 'A helpful class reference.',
            'price' => 250,
            'type' => 'sale',
            'category' => 'Books & Notes',
            'location' => 'Main campus',
            'seller_name' => $seller->name,
            'seller_initials' => 'ST',
        ]);

        $this->get(route('listings.messages.create', $listing))->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => $buyer->email,
            'password' => 'password',
        ])->assertRedirect(route('listings.messages.create', $listing));

        $this->get(route('listings.messages.create', $listing))
            ->assertRedirect(route('listings.show', $listing).'#contact');

        $this->get(route('listings.show', $listing))->assertOk()->assertSee('id="contact"', false);
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
