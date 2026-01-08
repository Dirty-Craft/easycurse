<?php

namespace Tests\Feature;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use App\Notifications\MinecraftVersionUpdateAvailable;
use App\Services\ModService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckMinecraftVersionUpdatesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command runs successfully when no reminders are set.
     */
    public function test_command_runs_successfully_with_no_reminders(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => null,
            'minecraft_update_reminder_software' => null,
        ]);

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();
    }

    /**
     * Test that notification is sent when all mods become compatible.
     */
    public function test_notification_sent_when_all_mods_compatible(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod 1',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod 2',
            'curseforge_mod_id' => 789012,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return files for both mods (all compatible)
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturn([
                ['id' => 1, 'displayName' => 'Test Mod 1.0.0'],
            ]);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class,
            function ($notification) use ($modPack) {
                return $notification->modPackName === $modPack->name
                    && $notification->targetMinecraftVersion === '1.21.1'
                    && $notification->targetSoftware === 'fabric'
                    && $notification->modPackId === $modPack->id;
            }
        );

        // Verify reminder fields are cleared
        $modPack->refresh();
        $this->assertNull($modPack->minecraft_update_reminder_version);
        $this->assertNull($modPack->minecraft_update_reminder_software);
    }

    /**
     * Test that notification is not sent when some mods are still incompatible.
     */
    public function test_notification_not_sent_when_some_mods_incompatible(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod 1',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod 2',
            'curseforge_mod_id' => 789012,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return files for first mod but empty for second (incompatible)
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturnCallback(function ($modId, $version, $software, $source) {
                if ($modId === 123456) {
                    return [['id' => 1, 'displayName' => 'Test Mod 1.0.0']];
                }

                return []; // No files for mod 789012
            });

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was not sent
        Notification::assertNothingSent();

        // Verify reminder fields are still set
        $modPack->refresh();
        $this->assertEquals('1.21.1', $modPack->minecraft_update_reminder_version);
        $this->assertEquals('fabric', $modPack->minecraft_update_reminder_software);
    }

    /**
     * Test that notification is not sent when mod pack has no items.
     */
    public function test_notification_not_sent_when_no_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        // No items in mod pack

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent (all 0 mods are compatible)
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class
        );

        // Verify reminder fields are cleared
        $modPack->refresh();
        $this->assertNull($modPack->minecraft_update_reminder_version);
        $this->assertNull($modPack->minecraft_update_reminder_software);
    }

    /**
     * Test that command handles Modrinth mods correctly.
     */
    public function test_command_handles_modrinth_mods(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Modrinth Mod',
            'modrinth_project_id' => 'abc123',
            'source' => 'modrinth',
        ]);

        // Mock ModService to return files for Modrinth mod
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturn([
                ['id' => 'version1', 'version_number' => '1.0.0'],
            ]);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class
        );
    }

    /**
     * Test that command handles items without source metadata.
     */
    public function test_command_handles_items_without_source(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'curseforge_mod_id' => 123456,
            'source' => null, // No source set
        ]);

        // Mock ModService to return files
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturn([
                ['id' => 1, 'displayName' => 'Test Mod 1.0.0'],
            ]);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class
        );
    }

    /**
     * Test that command handles errors gracefully.
     */
    public function test_command_handles_errors_gracefully(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to throw an exception
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willThrowException(new \Exception('API Error'));

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        // Command should still complete successfully (errors are logged but don't stop execution)
        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was not sent due to error
        Notification::assertNothingSent();
    }
}
