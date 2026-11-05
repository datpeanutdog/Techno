<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The PASABUY homepage renders successfully.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk()->assertSee('What do you need for school?');
    }
}
