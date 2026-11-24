<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The Pasabuy homepage renders successfully.
     */
    public function test_the_application_shows_the_public_welcome_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('USTP student marketplace')
            ->assertSee('Create your account')
            ->assertSee('name="password_confirmation"', false)
            ->assertSee(route('marketplace.index', ['panel' => 'login']), false)
            ->assertDontSee('Good finds, shared by students like you.')
            ->assertDontSee('Search campus listings');

        $this->get('/?panel=login')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee(route('support.reports.index'), false)
            ->assertSee('Contact support')
            ->assertDontSee('name="password_confirmation"', false);
    }
}
