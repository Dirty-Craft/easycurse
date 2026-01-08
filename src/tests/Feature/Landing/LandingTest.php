<?php

namespace Tests\Feature\Landing;

use App\Models\ModPack;
use App\Models\User;
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

    /**
     * Test that total_downloads is the sum of downloads_count from all mod packs.
     */
    public function test_total_downloads_is_sum_of_downloads_count(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create mod packs with different download counts
        $modPack1 = ModPack::factory()->create([
            'user_id' => $user1->id,
            'downloads_count' => 5,
        ]);
        $modPack2 = ModPack::factory()->create([
            'user_id' => $user1->id,
            'downloads_count' => 10,
        ]);
        $modPack3 = ModPack::factory()->create([
            'user_id' => $user2->id,
            'downloads_count' => 15,
        ]);

        $expectedTotal = 5 + 10 + 15; // 30

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('stats.total_downloads', $expectedTotal)
        );
    }

    /**
     * Test that total_downloads is zero when no mod packs exist.
     */
    public function test_total_downloads_is_zero_when_no_mod_packs_exist(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('stats.total_downloads', 0)
        );
    }

    /**
     * Test that total_downloads handles mod packs with zero downloads correctly.
     */
    public function test_total_downloads_handles_zero_downloads(): void
    {
        $user = User::factory()->create();

        // Create mod packs with zero downloads
        ModPack::factory()->count(3)->create([
            'user_id' => $user->id,
            'downloads_count' => 0,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Index')
            ->where('stats.total_downloads', 0)
        );
    }
}
