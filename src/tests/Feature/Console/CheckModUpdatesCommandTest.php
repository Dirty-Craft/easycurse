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
     * Also covers line 76: source inferred as 'modrinth' when source is null and curseforge_mod_id is null.
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

        // Item with null source and only modrinth_project_id (covers line 76: $source = 'modrinth')
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Modrinth Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => null,
            'modrinth_project_id' => 'null-source-modrinth-id',
            'source' => null,
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

        // Verify notification was sent and toMail/toArray methods are called
        Notification::assertSentTo(
            $user,
            ModUpdateAvailable::class,
            function ($notification) use ($modPack, $user) {
                // Verify properties
                $propertiesMatch = $notification->modName === 'Test Mod'
                    && $notification->currentVersion === '1.0.0'
                    && $notification->newVersion === 'Test Mod 1.1.0'
                    && $notification->software === 'forge'
                    && $notification->minecraftVersion === '1.20.1'
                    && $notification->modPackId === $modPack->id
                    && $notification->modPackName === $modPack->name;

                // Call toMail to ensure it's covered (lines 42-52)
                $mailMessage = $notification->toMail($user);
                $this->assertNotNull($mailMessage);
                $this->assertEquals('Mod Update Available', $mailMessage->subject);

                // Call toArray to ensure it's covered (lines 59-69)
                $array = $notification->toArray($user);
                $this->assertIsArray($array);
                $this->assertEquals('Test Mod', $array['mod_name']);
                $this->assertEquals('1.0.0', $array['current_version']);
                $this->assertEquals('Test Mod 1.1.0', $array['new_version']);
                $this->assertEquals('forge', $array['software']);
                $this->assertEquals('1.20.1', $array['minecraft_version']);
                $this->assertEquals($modPack->id, $array['mod_pack_id']);
                $this->assertEquals($modPack->name, $array['mod_pack_name']);

                return $propertiesMatch;
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
     * Uses source=null so line 76 is hit: $source = $item->source ?? ($item->curseforge_mod_id ? 'curseforge' : 'modrinth') => 'modrinth'.
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
            'curseforge_mod_id' => null,
            'modrinth_project_id' => 'test-mod-id',
            'source' => null, // Infer source from modrinth_project_id (line 76)
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

        // Verify notification was sent and toMail/toArray methods are called
        Notification::assertSentTo(
            $user,
            ModUpdateAvailable::class,
            function ($notification) use ($modPack, $user) {
                // Verify properties
                $propertiesMatch = $notification->modName === 'Test Mod'
                    && $notification->currentVersion === '1.0.0'
                    && $notification->newVersion === '1.1.0'
                    && $notification->modPackId === $modPack->id
                    && $notification->modPackName === $modPack->name;

                // Call toMail to ensure it's covered (lines 42-52)
                $mailMessage = $notification->toMail($user);
                $this->assertNotNull($mailMessage);

                // Call toArray to ensure it's covered (lines 59-69)
                $array = $notification->toArray($user);
                $this->assertIsArray($array);

                return $propertiesMatch;
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

    /**
     * Test that command skips items when modId is null (line 76).
     */
    public function test_command_skips_items_when_mod_id_is_null(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create item with both mod IDs null (line 76)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => null,
            'modrinth_project_id' => null,
            'source' => 'curseforge',
        ]);

        // Mock ModService - should not be called since modId is null
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->expects($this->never())
            ->method('getLatestModFile');

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify no notifications were sent
        Notification::assertNothingSent();
    }

    /**
     * Test that command skips when extractVersion returns null (line 94).
     */
    public function test_command_skips_when_extract_version_returns_null(): void
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

        // Mock ModService to return a file without displayName or fileName (line 94)
        $latestFile = [
            'id' => 1,
            // No displayName or fileName - extractVersion will return null
        ];

        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getLatestModFile')
            ->willReturn($latestFile);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('mods:check-updates')
            ->assertSuccessful();

        // Verify no notifications were sent (version extraction failed)
        Notification::assertNothingSent();
    }

    /**
     * Test that when same mod id appears with different sources, only the matching source row is processed.
     * ModPack has curseforge 123456; ModPack2 has same id with source=modrinth (no modrinth_project_id).
     * Only the curseforge grouped row yields a notification (line 119 is defensive when item lookup fails).
     */
    public function test_command_skips_when_mod_pack_item_not_found(): void
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

        $modPack2 = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Same mod id but stored with source=modrinth (no modrinth_project_id). Grouped rows:
        // (123456, null, curseforge) and (123456, null, modrinth). For modrinth row we get
        // modId=123456, source=modrinth. findAffectedModPacks(123456, 'modrinth') returns mod packs
        // with modrinth_project_id=123456. This item has modrinth_project_id=null, so modPack2
        // is not returned. So only curseforge row is processed; one notification.
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack2->id,
            'mod_name' => 'Test Mod 2',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'source' => 'modrinth',
        ]);

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

        Notification::assertSentTo($user, ModUpdateAvailable::class);
    }
}
