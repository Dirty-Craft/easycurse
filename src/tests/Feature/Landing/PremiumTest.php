<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that premium page is accessible.
     */
    public function test_premium_page_is_accessible(): void
    {
        $response = $this->get('/premium');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Premium'));
    }
}
