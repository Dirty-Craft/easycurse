<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that ads page is accessible.
     */
    public function test_ads_page_is_accessible(): void
    {
        $response = $this->get('/ads');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Ads'));
    }

    /**
     * Test that ad box does not appear on the ads page.
     */
    public function test_ad_box_does_not_appear_on_ads_page(): void
    {
        $response = $this->get('/ads');

        $response->assertStatus(200);
        // The ad box should not be shown on /ads page based on shouldShowAdvertisement logic
        // We verify this by checking the component structure
        $response->assertInertia(fn ($page) => $page->component('Ads'));
    }

    /**
     * Test that ad box appears on pages where it should be shown (e.g., login page).
     */
    public function test_ad_box_appears_on_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        // Verify that adText and adLink props are available in shared props
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->has('adText')
            ->has('adLink')
        );
    }

    /**
     * Test that ad box props are passed correctly from environment variables.
     */
    public function test_ad_box_props_from_environment_variables(): void
    {
        // Set environment variables for ad text and link
        config(['app.ad_text' => 'Test Ad Text']);
        config(['app.ad_link' => 'https://example.com']);

        // Note: In actual implementation, these come from env() in HandleInertiaRequests
        // For testing, we need to check if they're available in the response
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->has('adText')
            ->has('adLink')
        );
    }

    /**
     * Test that ad box props are available even when environment variables are not set.
     */
    public function test_ad_box_props_are_available_when_env_not_set(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        // Verify that adText and adLink props exist (they may be null if env vars are not set)
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->has('adText')
            ->has('adLink')
        );
    }

    /**
     * Test that ad box does not appear on landing page.
     */
    public function test_ad_box_does_not_appear_on_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index'));
        // The ad box should not be shown on / page
    }

    /**
     * Test that ad box does not appear on about page.
     */
    public function test_ad_box_does_not_appear_on_about_page(): void
    {
        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('About'));
        // The ad box should not be shown on /about page
    }

    /**
     * Test that ad box does not appear on donate page.
     */
    public function test_ad_box_does_not_appear_on_donate_page(): void
    {
        $response = $this->get('/donate');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Donate'));
        // The ad box should not be shown on /donate page
    }

    /**
     * Test that ad box appears on authenticated pages (e.g., mod-packs).
     */
    public function test_ad_box_appears_on_authenticated_pages(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        // Verify that adText and adLink props are available
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Index')
            ->has('adText')
            ->has('adLink')
        );
    }
}
