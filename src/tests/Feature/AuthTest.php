<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that login page is accessible to guests.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    /**
     * Test that authenticated users are redirected from login page.
     */
    public function test_authenticated_users_are_redirected_from_login(): void
    {
        $user = User::factory()->create();

        // Bypass guest middleware to test controller code (covers line 20)
        $response = $this->withoutMiddleware(\Illuminate\Auth\Middleware\RedirectIfAuthenticated::class)
            ->actingAs($user)->get('/login');

        $response->assertRedirect('/mod-packs');
        // Verify Auth::check() path is covered (line 20)
        $this->assertAuthenticated();
    }

    /**
     * Test successful login.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/mod-packs');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test login requires email.
     */
    public function test_login_requires_email(): void
    {
        $response = $this->post('/login', [
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test login requires password.
     */
    public function test_login_requires_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test login with remember me.
     */
    public function test_user_can_login_with_remember_me(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertRedirect('/mod-packs');
        $this->assertAuthenticatedAs($user);
        // Check that remember token is set
        $this->assertNotNull($user->fresh()->remember_token);
    }

    /**
     * Test that register page is accessible to guests.
     */
    public function test_register_page_is_accessible(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    /**
     * Test that authenticated users are redirected from register page.
     */
    public function test_authenticated_users_are_redirected_from_register(): void
    {
        $user = User::factory()->create();

        // Bypass guest middleware to test controller code (covers line 53)
        $response = $this->withoutMiddleware(\Illuminate\Auth\Middleware\RedirectIfAuthenticated::class)
            ->actingAs($user)->get('/register');

        $response->assertRedirect('/mod-packs');
        // Verify Auth::check() path is covered (line 53)
        $this->assertAuthenticated();
    }

    /**
     * Test successful registration.
     */
    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/mod-packs');
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $this->assertAuthenticated();
    }

    /**
     * Test registration requires name.
     */
    public function test_registration_requires_name(): void
    {
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test registration requires email.
     */
    public function test_registration_requires_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test registration requires unique email.
     */
    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test registration requires password.
     */
    public function test_registration_requires_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test registration requires password confirmation.
     */
    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test registration requires minimum password length.
     */
    public function test_registration_requires_minimum_password_length(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test user can logout.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test logout requires authentication.
     */
    public function test_logout_requires_authentication(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect('/login');
    }

    /**
     * Test login redirects to intended page after authentication.
     */
    public function test_login_redirects_to_intended_page(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->get('/mod-packs');
        $response->assertRedirect('/login');

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/mod-packs');
    }

    /**
     * Test that forgot password page is accessible to guests.
     */
    public function test_forgot_password_page_is_accessible(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    /**
     * Test that authenticated users are redirected from forgot password page.
     */
    public function test_authenticated_users_are_redirected_from_forgot_password(): void
    {
        $user = User::factory()->create();

        $response = $this->withoutMiddleware(\Illuminate\Auth\Middleware\RedirectIfAuthenticated::class)
            ->actingAs($user)->get('/forgot-password');

        $response->assertRedirect('/mod-packs');
    }

    /**
     * Test user can request password reset with valid email.
     */
    public function test_user_can_request_password_reset_with_valid_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Visit the page first so back() knows where to redirect
        $this->get('/forgot-password');

        $response = $this->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, \App\Notifications\ResetPasswordNotification::class);
    }

    /**
     * Test forgot password requires email.
     */
    public function test_forgot_password_requires_email(): void
    {
        $response = $this->post('/forgot-password', []);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test forgot password requires valid email format.
     */
    public function test_forgot_password_requires_valid_email_format(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test reset password page is accessible with token.
     */
    public function test_reset_password_page_is_accessible_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->get("/reset-password/{$token}?email=test@example.com");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', $token)
            ->where('email', 'test@example.com')
        );
    }

    /**
     * Test that authenticated users are redirected from reset password page.
     */
    public function test_authenticated_users_are_redirected_from_reset_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->withoutMiddleware(\Illuminate\Auth\Middleware\RedirectIfAuthenticated::class)
            ->actingAs($user)->get("/reset-password/{$token}?email={$user->email}");

        $response->assertRedirect('/mod-packs');
    }

    /**
     * Test user can reset password with valid token.
     */
    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    /**
     * Test reset password requires token.
     */
    public function test_reset_password_requires_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/reset-password', [
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('token');
    }

    /**
     * Test reset password requires email.
     */
    public function test_reset_password_requires_email(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    /**
     * Test reset password requires password.
     */
    public function test_reset_password_requires_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test reset password requires password confirmation.
     */
    public function test_reset_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test reset password requires minimum password length.
     */
    public function test_reset_password_requires_minimum_password_length(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * Test reset password fails with invalid token.
     */
    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

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
