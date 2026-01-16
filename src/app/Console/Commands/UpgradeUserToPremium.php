<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpgradeUserToPremium extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:upgrade-premium
                            {user : The user ID or email address}
                            {--until= : Set premium until a specific date (Y-m-d format or relative date string)}
                            {--days= : Add number of days to current premium expiration (or from now if not premium)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade a user to premium or extend their premium subscription';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userIdentifier = $this->argument('user');

        // Get options - check if they were provided using input() to detect '0' values
        $input = $this->input;
        $hasUntilOption = $input->hasOption('until') && $input->getOption('until') !== null;
        $hasDaysOption = $input->hasOption('days') && $input->getOption('days') !== null;

        $untilDate = $hasUntilOption ? $this->option('until') : null;
        $daysToAdd = $hasDaysOption ? $this->option('days') : null;

        // Validate that exactly one of --until or --days is provided
        if ($hasUntilOption && $hasDaysOption) {
            $this->error('Cannot specify both --until and --days options. Please use only one.');

            return Command::FAILURE;
        }

        if (! $hasUntilOption && ! $hasDaysOption) {
            $this->error('Either --until or --days option must be provided.');

            return Command::FAILURE;
        }

        // Find the user by ID or email
        $user = $this->findUser($userIdentifier);
        if (! $user) {
            return Command::FAILURE;
        }

        // Calculate the new premium expiration date
        $newPremiumUntil = $this->calculateNewPremiumDate($user, $untilDate, $daysToAdd);
        if (! $newPremiumUntil) {
            return Command::FAILURE;
        }

        // Display user information and preview
        $this->displayUserInfo($user, $newPremiumUntil);

        // Ask for confirmation
        if (! $this->confirm('Do you want to proceed with this upgrade?', true)) {
            $this->info('Upgrade cancelled.');

            return Command::SUCCESS;
        }

        // Update the user's premium status
        $user->premium_until = $newPremiumUntil;
        $user->save();

        $this->info('✓ User premium status updated successfully!');
        $this->line("   Premium expires on: {$newPremiumUntil->format('Y-m-d H:i:s')}");

        return Command::SUCCESS;
    }

    /**
     * Find user by ID or email.
     */
    private function findUser(string $identifier): ?User
    {
        // Try to find by ID first
        if (is_numeric($identifier)) {
            $user = User::find($identifier);
            if ($user) {
                return $user;
            }
        }

        // Try to find by email
        $user = User::where('email', $identifier)->first();
        if ($user) {
            return $user;
        }

        $this->error("User not found: {$identifier}");

        return null;
    }

    /**
     * Calculate the new premium expiration date.
     */
    private function calculateNewPremiumDate(User $user, ?string $untilDate, ?string $daysToAdd): ?Carbon
    {
        if ($untilDate) {
            // Parse the until date
            try {
                $parsedDate = Carbon::parse($untilDate);
                if (! $parsedDate->isFuture() && $parsedDate->format('Y-m-d H:i:s') !== Carbon::now()->format('Y-m-d H:i:s')) {
                    // Allow setting to now or future dates
                    if ($parsedDate->isPast() && ! $parsedDate->isToday()) {
                        $this->warn("Warning: The date you provided ({$parsedDate->format('Y-m-d')}) is in the past.");
                        if (! $this->confirm('Do you want to proceed anyway?', false)) {
                            return null;
                        }
                    }
                }

                return $parsedDate;
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$untilDate}. Please use a valid date format (e.g., '2024-12-31', 'next month', '+30 days').");

                return null;
            }
        }

        if ($daysToAdd !== null) {
            // Validate that daysToAdd is a positive number
            if (! is_numeric($daysToAdd) || (int) $daysToAdd <= 0) {
                $this->error("Invalid number of days: {$daysToAdd}. Please provide a positive integer.");

                return null;
            }

            $days = (int) $daysToAdd;

            // If user already has premium, add days to their current expiration
            if ($user->premium_until && $user->premium_until->isFuture()) {
                return $user->premium_until->copy()->addDays($days);
            }

            // Otherwise, add days from now
            return Carbon::now()->addDays($days);
        }

        return null;
    }

    /**
     * Display user information and preview of changes.
     */
    private function displayUserInfo(User $user, Carbon $newPremiumUntil): void
    {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════');
        $this->info('User Information');
        $this->line('═══════════════════════════════════════════════════════════');
        $this->line("ID:        {$user->id}");
        $this->line("Name:      {$user->name}");
        $this->line("Email:     {$user->email}");
        $this->line('───────────────────────────────────────────────────────────');

        // Current premium status
        $currentStatus = 'Not Premium';
        $currentExpiration = 'N/A';
        if ($user->premium_until) {
            if ($user->premium_until->isFuture()) {
                $currentStatus = 'Premium (Active)';
                $currentExpiration = $user->premium_until->format('Y-m-d H:i:s');
            } else {
                $currentStatus = 'Premium (Expired)';
                $currentExpiration = $user->premium_until->format('Y-m-d H:i:s');
            }
        }

        $this->info('Current Premium Status:');
        $this->line("   Status:     {$currentStatus}");
        $this->line("   Expires:    {$currentExpiration}");
        $this->line('───────────────────────────────────────────────────────────');

        // New premium status preview
        $this->info('New Premium Status (Preview):');
        $newStatus = $newPremiumUntil->isFuture() ? 'Premium (Active)' : 'Premium (Expired)';
        $this->line("   Status:     {$newStatus}");
        $this->line("   Expires:    {$newPremiumUntil->format('Y-m-d H:i:s')}");

        // Calculate days difference if applicable
        if ($user->premium_until && $user->premium_until->isFuture()) {
            $daysDifference = $user->premium_until->diffInDays($newPremiumUntil, false);
            if ($daysDifference > 0) {
                $this->line("   Extension:  +{$daysDifference} days");
            } elseif ($daysDifference < 0) {
                $this->line("   Change:     {$daysDifference} days");
            } else {
                $this->line('   Change:     No change');
            }
        } else {
            $daysFromNow = Carbon::now()->diffInDays($newPremiumUntil, false);
            $this->line("   Duration:   {$daysFromNow} days from now");
        }

        $this->line('═══════════════════════════════════════════════════════════');
        $this->newLine();
    }
}
