<?php

namespace Tests\Feature\Console;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use App\Notifications\ModUpdateAvailable;
use App\Services\ModService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckModUpdatesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command runs successfully.
     */
    public function test_command_runs_successfully(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return null (no update available)
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willReturn(null);

        $this->app->instance(ModService::class, $modServiceMock);

        $this->artisan('mods:check-updates')
            ->assertSuccessful();
    }

    /**
     * Test that update detection logic is triggered.
     */
    public function test_update_detection_logic_is_triggered(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return a newer version
        $latestFile = [
            'displayName' => 'Test Mod 1.1.0',
            'fileName' => 'test-mod-1.1.0.jar',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->with(123456, '1.20.1', 'forge', 'curseforge')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify notification was sent
        Notification::assertSentTo(
            $user,
            ModUpdateAvailable::class,
            function ($notification) use ($modPack) {
                return $notification->modName === 'Test Mod'
                    && $notification->currentVersion === '1.0.0'
                    && $notification->newVersion === 'Test Mod 1.1.0'
                    && $notification->software === 'forge'
                    && $notification->minecraftVersion === '1.20.1'
                    && $notification->modPackId === $modPack->id
                    && $notification->modPackName === $modPack->name;
            }
        );
    }

    /**
     * Test that notifications are sent when a newer version is found.
     */
    public function test_notifications_sent_when_newer_version_found(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create two mod packs with the same mod
        $modPack1 = ModPack::factory()->create([
            'user_id' => $user1->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $modPack2 = ModPack::factory()->create([
            'user_id' => $user2->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack1->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack2->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return a newer version
        $latestFile = [
            'displayName' => 'Test Mod 1.2.0',
            'fileName' => 'test-mod-1.2.0.jar',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->with(123456, '1.20.1', 'forge', 'curseforge')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify notifications were sent to both users
        Notification::assertSentTo($user1, ModUpdateAvailable::class);
        Notification::assertSentTo($user2, ModUpdateAvailable::class);
        Notification::assertCount(2, ModUpdateAvailable::class);
    }

    /**
     * Test that no notifications are sent when no update is available.
     */
    public function test_no_notifications_when_no_update_available(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return the same version (no update)
        $latestFile = [
            'displayName' => 'Test Mod 1.0.0',
            'fileName' => 'test-mod-1.0.0.jar',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify no notifications were sent
        Notification::assertNothingSent();
    }

    /**
     * Test that Modrinth mods are also checked.
     */
    public function test_modrinth_mods_are_checked(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'modrinth_project_id' => 'test-mod-id',
            'source' => 'modrinth',
        ]);

        // Mock ModService to return a newer version
        $latestVersion = [
            'version_number' => '1.1.0',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->with('test-mod-id', '1.20.1', 'fabric', 'modrinth')
            ->willReturn($latestVersion);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify notification was sent
        Notification::assertSentTo(
            $user,
            ModUpdateAvailable::class,
            function ($notification) use ($modPack) {
                return $notification->modName === 'Test Mod'
                    && $notification->currentVersion === '1.0.0'
                    && $notification->newVersion === '1.1.0'
                    && $notification->modPackId === $modPack->id
                    && $notification->modPackName === $modPack->name;
            }
        );
    }

    /**
     * Test that the command handles errors gracefully.
     */
    public function test_command_handles_errors_gracefully(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to throw an exception
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willThrowException(new \Exception('API Error'));

        $this->app->instance(ModService::class, $modServiceMock);

        // Command should still complete successfully (errors are logged)
        $this->artisan('mods:check-updates')
            ->assertSuccessful();
    }

    /**
     * Test that notifications are not sent if already notified within 1 month.
     */
    public function test_no_notification_if_recently_notified(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create a mod pack item that was notified 2 weeks ago
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
            'last_update_notified_at' => Carbon::now()->subWeeks(2),
        ]);

        // Mock ModService to return a newer version
        $latestFile = [
            'displayName' => 'Test Mod 1.1.0',
            'fileName' => 'test-mod-1.1.0.jar',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify no notifications were sent (already notified within 1 month)
        Notification::assertNothingSent();
    }

    /**
     * Test that notifications are sent again after 1 month cooldown.
     */
    public function test_notification_sent_after_1_month_cooldown(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create a mod pack item that was notified 5 weeks ago (more than 1 month)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
            'last_update_notified_at' => Carbon::now()->subWeeks(5),
        ]);

        // Mock ModService to return a newer version
        $latestFile = [
            'displayName' => 'Test Mod 1.1.0',
            'fileName' => 'test-mod-1.1.0.jar',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify notification was sent (cooldown period has passed)
        Notification::assertSentTo($user, ModUpdateAvailable::class);
    }

    /**
     * Test that last_update_notified_at is updated after sending notification.
     */
    public function test_last_update_notified_at_is_updated(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $modPackItem = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
            'last_update_notified_at' => null,
        ]);

        // Mock ModService to return a newer version
        $latestFile = [
            'displayName' => 'Test Mod 1.1.0',
            'fileName' => 'test-mod-1.1.0.jar',
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify last_update_notified_at was updated
        $modPackItem->refresh();
        $this->assertNotNull($modPackItem->last_update_notified_at);
        $this->assertTrue($modPackItem->last_update_notified_at->isToday());
    }
}
