<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

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

        // Test that toMail method is called and renders correctly (covers ResetPasswordNotification lines 44-65)
        Notification::assertSentTo($user, \App\Notifications\ResetPasswordNotification::class, function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);
            $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $mail);
            $this->assertEquals('Reset Password Notification', $mail->subject);

            // Test toArray method (covers ResetPasswordNotification lines 64-65)
            $array = $notification->toArray($notifiable);
            $this->assertIsArray($array);
            $this->assertEmpty($array);

            return true;
        });
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
     * Test forgot password handles error when user doesn't exist.
     * Covers AuthController lines 124-126.
     */
    public function test_forgot_password_handles_nonexistent_user(): void
    {
        // Visit the page first so back() knows where to redirect
        $this->get('/forgot-password');

        // Try to request password reset for non-existent user
        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        // Should redirect back with validation error
        $response->assertSessionHasErrors('email');
    }
}
