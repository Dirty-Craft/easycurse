<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that profile page requires authentication.
     */
    public function test_profile_page_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    /**
     * Test that profile page is accessible to authenticated users.
     */
    public function test_profile_page_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Profile')
            ->has('user')
            ->where('user.id', $user->id)
            ->where('user.name', $user->name)
            ->where('user.email', $user->email)
        );
    }

    /**
     * Test user can update profile.
     */
    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
        ]);

        // Visit the page first so back() knows where to redirect
        $this->actingAs($user)->get('/profile');

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test profile update requires name.
     */
    public function test_profile_update_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', []);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test profile update validates name max length.
     */
    public function test_profile_update_validates_name_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors('name');
    }
}
