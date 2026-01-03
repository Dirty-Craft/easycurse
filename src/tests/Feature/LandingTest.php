<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test that about page is accessible.
     */
    public function test_about_page_is_accessible(): void
    {
        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('About'));
    }

    /**
     * Test language switcher with valid language query parameter (English).
     */
    public function test_language_switcher_with_valid_english_query_parameter(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('locale', 'en')
        );

        // Verify cookie is set
        $response->assertCookie('lang', 'en');

        // Verify locale is set in the application
        $this->assertEquals('en', app()->getLocale());
    }

    /**
     * Test language switcher with valid language query parameter (Farsi).
     */
    public function test_language_switcher_with_valid_farsi_query_parameter(): void
    {
        $response = $this->get('/?lang=fa');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('locale', 'fa')
        );

        // Verify cookie is set
        $response->assertCookie('lang', 'fa');

        // Verify locale is set in the application
        $this->assertEquals('fa', app()->getLocale());
    }

    /**
     * Test language switcher with invalid language query parameter.
     */
    public function test_language_switcher_with_invalid_query_parameter(): void
    {
        $response = $this->get('/?lang=invalid');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('locale', 'en')
        );

        // Verify cookie is set to default
        $response->assertCookie('lang', 'en');

        // Verify locale is set to default
        $this->assertEquals('en', app()->getLocale());
    }

    /**
     * Test language switcher with valid language cookie (Farsi).
     */
    public function test_language_switcher_with_valid_farsi_cookie(): void
    {
        $response = $this->withCookie('lang', 'fa')
            ->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('locale', 'fa')
        );

        // Verify locale is set in the application
        $this->assertEquals('fa', app()->getLocale());
    }

    /**
     * Test language switcher with invalid language cookie.
     */
    public function test_language_switcher_with_invalid_cookie(): void
    {
        $response = $this->withCookie('lang', 'invalid')
            ->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('locale', 'en')
        );

        // Verify locale is set to default
        $this->assertEquals('en', app()->getLocale());
    }

    /**
     * Test that stats section is present on landing page.
     */
    public function test_stats_section_is_present(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->has('stats')
            ->has('stats.total_mod_packs')
            ->has('stats.total_users')
            ->has('stats.total_downloads')
        );
    }

    /**
     * Test that stats data is correctly passed to the view.
     */
    public function test_stats_data_is_correctly_passed(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('stats.total_mod_packs', fn ($value) => is_int($value) && $value >= 0)
            ->where('stats.total_users', fn ($value) => is_int($value) && $value >= 0)
            ->where('stats.total_downloads', fn ($value) => is_int($value) && $value >= 0)
        );
    }
}
