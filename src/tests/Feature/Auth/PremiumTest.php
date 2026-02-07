<?php

namespace Tests\Feature\Auth;

use App\Models\ModPack;
use App\Models\ModPackRun;
use App\Models\User;
use Tests\TestCase;

class PremiumTest extends TestCase
{
    /**
     * Test that free user can create runs up to the limit.
     */
    public function test_free_user_can_create_runs_up_to_limit(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 10 runs (the limit for free users)
        ModPackRun::factory()->count(9)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should succeed (this is the 10th run)
        $response->assertStatus(302);
        $this->assertDatabaseCount('mod_pack_runs', 10);
    }

    /**
     * Test that free user is redirected to premium page when limit is exceeded.
     */
    public function test_free_user_redirected_to_premium_when_limit_exceeded(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 10 runs (the limit for free users)
        ModPackRun::factory()->count(10)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should redirect to premium page
        $response->assertRedirect(route('premium'));
        $response->assertSessionHas('error');
        // Should not create another run
        $this->assertDatabaseCount('mod_pack_runs', 10);
    }

    /**
     * Test that premium user can create unlimited runs.
     */
    public function test_premium_user_can_create_unlimited_runs(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->addDays(30),
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 20 runs (more than the free limit)
        ModPackRun::factory()->count(20)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should succeed
        $response->assertStatus(302);
        $this->assertDatabaseCount('mod_pack_runs', 21);
    }

    /**
     * Test that user with expired premium is treated as free.
     */
    public function test_user_with_expired_premium_is_treated_as_free(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->subDays(1),
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 10 runs (the limit for free users)
        ModPackRun::factory()->count(10)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should redirect to premium page
        $response->assertRedirect(route('premium'));
        $this->assertDatabaseCount('mod_pack_runs', 10);
    }

    /**
     * Test that monthly run count resets each month.
     */
    public function test_monthly_run_count_resets_each_month(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 10 runs in previous month
        ModPackRun::factory()->count(10)->create([
            'mod_pack_id' => $modPack->id,
            'created_at' => now()->subMonth(),
        ]);

        // Should be able to create runs in current month
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/runs");

        // Should succeed
        $response->assertStatus(302);
        $this->assertDatabaseCount('mod_pack_runs', 11);
    }

    /**
     * Test that profile page shows account type for free user.
     */
    public function test_profile_page_shows_account_type_for_free_user(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Profile')
            ->where('isPremium', false)
            ->where('monthlyRunCount', 0)
        );
    }

    /**
     * Test that profile page shows account type for premium user.
     */
    public function test_profile_page_shows_account_type_for_premium_user(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Profile')
            ->where('isPremium', true)
        );
    }

    /**
     * Test that profile page shows monthly run count.
     */
    public function test_profile_page_shows_monthly_run_count(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 5 runs this month
        ModPackRun::factory()->count(5)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Profile')
            ->where('monthlyRunCount', 5)
        );
    }

    /**
     * Test that isPremium method returns true for premium users.
     */
    public function test_is_premium_returns_true_for_premium_users(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->addDays(30),
        ]);

        $this->assertTrue($user->isPremium());
    }

    /**
     * Test that isPremium method returns false for free users.
     */
    public function test_is_premium_returns_false_for_free_users(): void
    {
        $user = User::factory()->create([
            'premium_until' => null,
        ]);

        $this->assertFalse($user->isPremium());
    }

    /**
     * Test that isPremium method returns false for expired premium users.
     */
    public function test_is_premium_returns_false_for_expired_premium_users(): void
    {
        $user = User::factory()->create([
            'premium_until' => now()->subDays(1),
        ]);

        $this->assertFalse($user->isPremium());
    }

    /**
     * Test that getMonthlyRunCount returns correct count.
     */
    public function test_get_monthly_run_count_returns_correct_count(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create 3 runs this month
        ModPackRun::factory()->count(3)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        // Create 2 runs last month (should not be counted)
        ModPackRun::factory()->count(2)->create([
            'mod_pack_id' => $modPack->id,
            'created_at' => now()->subMonth(),
        ]);

        $this->assertEquals(3, $user->getMonthlyRunCount());
    }
}
