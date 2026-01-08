<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that change password page requires authentication.
     */
    public function test_change_password_page_requires_authentication(): void
    {
        $response = $this->get('/change-password');

        $response->assertRedirect('/login');
    }

    /**
     * Test that change password page is accessible to authenticated users.
     */
    public function test_change_password_page_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/change-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ChangePassword'));
    }

    /**
     * Test user can change password with valid current password.
     */
    public function test_user_can_change_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password123'),
        ]);

        // Visit the page first so back() knows where to redirect
        $this->actingAs($user)->get('/change-password');

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'old-password123',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect('/change-password');
        $response->assertSessionHas('status');
        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    /**
     * Test change password requires current password.
     */
    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/change-password', [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    /**
     * Test change password requires new password.
     */
    public function test_change_password_requires_new_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password123'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'old-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test change password requires password confirmation.
     */
    public function test_change_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password123'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'old-password123',
            'password' => 'new-password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test change password requires minimum password length.
     */
    public function test_change_password_requires_minimum_password_length(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password123'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'old-password123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test change password fails with incorrect current password.
     */
    public function test_change_password_fails_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password123'),
        ]);

        $response = $this->actingAs($user)->post('/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password123', $user->fresh()->password));
    }
}
