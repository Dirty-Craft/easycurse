<?php

namespace Tests\Feature\Landing;

use Tests\TestCase;

class AboutTest extends TestCase
{
    /**
     * Test that about page is accessible.
     */
    public function test_about_page_is_accessible(): void
    {
        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('About'));
    }
}
