<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeUserToPremiumCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command fails when user is not found.
     */
    public function test_command_fails_when_user_not_found(): void
    {
        $this->artisan('user:upgrade-premium', [
            'user' => '999',
            '--days' => '30',
        ])
            ->expectsOutput('User not found: 999')
            ->assertFailed();

        $this->artisan('user:upgrade-premium', [
            'user' => 'nonexistent@example.com',
            '--days' => '30',
        ])
            ->expectsOutput('User not found: nonexistent@example.com')
            ->assertFailed();
    }

    /**
     * Test that the command fails when neither --until nor --days is provided.
     */
    public function test_command_fails_when_no_option_provided(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
        ])
            ->expectsOutput('Either --until or --days option must be provided.')
            ->assertFailed();
    }

    /**
     * Test that the command fails when both --until and --days are provided.
     */
    public function test_command_fails_when_both_options_provided(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => '2024-12-31',
            '--days' => '30',
        ])
            ->expectsOutput('Cannot specify both --until and --days options. Please use only one.')
            ->assertFailed();
    }

    /**
     * Test that the command upgrades a non-premium user with --days option.
     */
    public function test_command_upgrades_non_premium_user_with_days(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '30',
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->premium_until);
        $this->assertTrue($user->premium_until->isFuture());
        $expectedDate = Carbon::now()->addDays(30);
        $this->assertEquals($expectedDate->format('Y-m-d'), $user->premium_until->format('Y-m-d'));
    }

    /**
     * Test that the command extends premium for an existing premium user with --days option.
     */
    public function test_command_extends_premium_with_days(): void
    {
        $currentExpiration = Carbon::now()->addDays(10);
        $user = User::factory()->create([
            'premium_until' => $currentExpiration,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '30',
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $expectedDate = $currentExpiration->copy()->addDays(30);
        $this->assertEquals($expectedDate->format('Y-m-d'), $user->premium_until->format('Y-m-d'));
    }

    /**
     * Test that the command upgrades a user with --until option.
     */
    public function test_command_upgrades_user_with_until_date(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $targetDate = Carbon::now()->addMonths(3);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => $targetDate->format('Y-m-d'),
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->premium_until);
        $this->assertEquals($targetDate->format('Y-m-d'), $user->premium_until->format('Y-m-d'));
    }

    /**
     * Test that the command can find user by email.
     */
    public function test_command_finds_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'premium_until' => null,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => 'test@example.com',
            '--days' => '30',
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->premium_until);
    }

    /**
     * Test that the command cancels when confirmation is denied.
     */
    public function test_command_cancels_when_confirmation_denied(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $originalPremiumUntil = $user->premium_until;

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '30',
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'no')
            ->expectsOutput('Upgrade cancelled.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertEquals($originalPremiumUntil, $user->premium_until);
    }

    /**
     * Test that the command handles invalid date format.
     */
    public function test_command_handles_invalid_date_format(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => 'invalid-date',
        ])
            ->expectsOutput('Invalid date format: invalid-date. Please use a valid date format (e.g., \'2024-12-31\', \'next month\', \'+30 days\').')
            ->assertFailed();
    }

    /**
     * Test that the command handles invalid days value.
     */
    public function test_command_handles_invalid_days(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '0',
        ])
            ->expectsOutputToContain('Invalid number of days: 0')
            ->assertFailed();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '-5',
        ])
            ->expectsOutputToContain('Invalid number of days: -5')
            ->assertFailed();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => 'not-a-number',
        ])
            ->expectsOutputToContain('Invalid number of days: not-a-number')
            ->assertFailed();
    }

    /**
     * Test that the command handles past dates with confirmation.
     */
    public function test_command_handles_past_date_with_confirmation(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $pastDate = Carbon::now()->subDays(5);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => $pastDate->format('Y-m-d'),
        ])
            ->expectsOutputToContain('Warning: The date you provided')
            ->expectsConfirmation('Do you want to proceed anyway?', 'yes')
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->premium_until);
    }

    /**
     * Test that the command cancels when past date confirmation is denied.
     */
    public function test_command_cancels_when_past_date_confirmation_denied(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $pastDate = Carbon::now()->subDays(5);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => $pastDate->format('Y-m-d'),
        ])
            ->expectsOutputToContain('Warning: The date you provided')
            ->expectsConfirmation('Do you want to proceed anyway?', 'no')
            ->assertFailed();

        $user->refresh();
        $this->assertNull($user->premium_until);
    }

    /**
     * Test that the command displays user information correctly.
     */
    public function test_command_displays_user_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'premium_until' => null,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '30',
        ])
            ->expectsOutputToContain('User Information')
            ->expectsOutputToContain("ID:        {$user->id}")
            ->expectsOutputToContain('Name:      Test User')
            ->expectsOutputToContain('Email:     testuser@example.com')
            ->expectsOutputToContain('Current Premium Status:')
            ->expectsOutputToContain('Not Premium')
            ->expectsOutputToContain('New Premium Status (Preview):')
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();
    }

    /**
     * Test that the command displays current premium status correctly.
     */
    public function test_command_displays_current_premium_status(): void
    {
        $currentExpiration = Carbon::now()->addDays(15);
        $user = User::factory()->create([
            'premium_until' => $currentExpiration,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '30',
        ])
            ->expectsOutputToContain('Premium (Active)')
            ->expectsOutputToContain($currentExpiration->format('Y-m-d'))
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();
    }

    /**
     * Test that the command displays expired premium status correctly.
     */
    public function test_command_displays_expired_premium_status(): void
    {
        $expiredDate = Carbon::now()->subDays(5);
        $user = User::factory()->create([
            'premium_until' => $expiredDate,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--days' => '30',
        ])
            ->expectsOutputToContain('Premium (Expired)')
            ->expectsOutputToContain($expiredDate->format('Y-m-d'))
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();
    }

    /**
     * Test that the command handles relative date strings.
     */
    public function test_command_handles_relative_date_strings(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => '+30 days',
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->premium_until);
        $expectedDate = Carbon::now()->addDays(30);
        $this->assertEquals($expectedDate->format('Y-m-d'), $user->premium_until->format('Y-m-d'));
    }

    /**
     * Test that the command handles today's date correctly.
     */
    public function test_command_handles_todays_date(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $today = Carbon::today();

        $this->artisan('user:upgrade-premium', [
            'user' => $user->id,
            '--until' => $today->format('Y-m-d'),
        ])
            ->expectsConfirmation('Do you want to proceed with this upgrade?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->premium_until);
    }
}
