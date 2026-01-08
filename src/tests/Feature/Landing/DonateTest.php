<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that donate page is accessible.
     */
    public function test_donate_page_is_accessible(): void
    {
        $response = $this->get('/donate');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Donate'));
    }

    /**
     * Test that donate page passes wallet address prop.
     */
    public function test_donate_page_passes_wallet_address_prop(): void
    {
        $response = $this->get('/donate');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Donate')
                ->has('walletAddress'),
        );
    }
}
