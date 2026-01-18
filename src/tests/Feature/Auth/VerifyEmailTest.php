<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that verification notice page is accessible to authenticated users.
     */
    public function test_verification_notice_page_is_accessible(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
    }

    /**
     * Test that verified users are redirected from verification notice page.
     */
    public function test_verified_users_are_redirected_from_verification_notice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertRedirect('/mod-packs');
    }

    /**
     * Test that guests cannot access verification notice page.
     */
    public function test_guests_cannot_access_verification_notice(): void
    {
        $response = $this->get('/email/verify');

        $response->assertRedirect('/login');
    }

    /**
     * Test user can verify email with valid verification link (as guest).
     */
    public function test_user_can_verify_email_with_valid_link(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    /**
     * Test authenticated user can verify email with valid verification link.
     */
    public function test_authenticated_user_can_verify_email_with_valid_link(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/mod-packs');
        $response->assertSessionHas('status');
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    /**
     * Test user cannot verify email with invalid hash.
     */
    public function test_user_cannot_verify_email_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'invalid-hash']
        );

        $response = $this->get($verificationUrl);

        $response->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Test user cannot verify email with invalid user id.
     */
    public function test_user_cannot_verify_email_with_invalid_user_id(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 99999, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertStatus(404);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Test already verified user is redirected to login (as guest).
     */
    public function test_already_verified_user_is_redirected_to_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status');
    }

    /**
     * Test already verified authenticated user is redirected to mod-packs.
     */
    public function test_already_verified_authenticated_user_is_redirected_to_mod_packs(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/mod-packs');
        $response->assertSessionHas('status');
    }

    /**
     * Test user can resend verification email.
     */
    public function test_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post('/email/verification-notification');

        $response->assertRedirect('/email/verify');
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, \App\Notifications\VerifyEmailNotification::class);
    }

    /**
     * Test verified user cannot resend verification email.
     */
    public function test_verified_user_cannot_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/email/verification-notification');

        $response->assertRedirect('/mod-packs');
        Notification::assertNothingSent();
    }

    /**
     * Test verification email notification renders correctly.
     */
    public function test_verification_email_notification_renders_correctly(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
        ]);

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, \App\Notifications\VerifyEmailNotification::class, function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);
            $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $mail);
            $this->assertNotEmpty($mail->subject);

            // Test toArray method
            $array = $notification->toArray($notifiable);
            $this->assertIsArray($array);
            $this->assertEmpty($array);

            return true;
        });
    }

    /**
     * Test unverified users are blocked from accessing protected routes.
     */
    public function test_unverified_users_are_blocked_from_protected_routes(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertRedirect('/email/verify');
    }

    /**
     * Test verified users can access protected routes.
     */
    public function test_verified_users_can_access_protected_routes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
    }

    /**
     * Test verification link expires after expiration time.
     */
    public function test_verification_link_expires_after_expiration_time(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
        ]);

        // Create URL that expired 1 minute ago
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($verificationUrl);

        $response->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
