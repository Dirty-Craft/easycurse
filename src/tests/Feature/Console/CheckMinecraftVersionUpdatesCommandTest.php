<?php

namespace Tests\Feature\Console;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use App\Notifications\MinecraftVersionUpdateAvailable;
use App\Services\ModService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckMinecraftVersionUpdatesCommandTest extends TestCase
{
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

        // Verify notification was sent and toMail/toArray methods are called
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class,
            function ($notification) use ($modPack, $user) {
                // Verify properties
                $propertiesMatch = $notification->modPackName === $modPack->name
                    && $notification->targetMinecraftVersion === '1.21.1'
                    && $notification->targetSoftware === 'fabric'
                    && $notification->modPackId === $modPack->id;

                // Call toMail to ensure it's covered (lines 39-47)
                $mailMessage = $notification->toMail($user);
                $this->assertNotNull($mailMessage);
                $this->assertEquals('Minecraft Version Update Available', $mailMessage->subject);

                // Call toArray to ensure it's covered (lines 54-61)
                $array = $notification->toArray($user);
                $this->assertIsArray($array);
                $this->assertEquals($modPack->name, $array['mod_pack_name']);
                $this->assertEquals('1.21.1', $array['target_minecraft_version']);
                $this->assertEquals('fabric', $array['target_software']);
                $this->assertEquals($modPack->id, $array['mod_pack_id']);

                return $propertiesMatch;
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
     * Covers inferring source from curseforge_mod_id (line 60) and modrinth_project_id (line 63).
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

        // Item with null source and curseforge_mod_id (covers line 60: $source = 'curseforge')
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'curseforge_mod_id' => 123456,
            'source' => null, // No source set
        ]);

        // Item with null source and only modrinth_project_id (covers line 63: $source = 'modrinth')
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Modrinth Mod Inferred',
            'curseforge_mod_id' => null,
            'modrinth_project_id' => 'inferred-modrinth-id',
            'source' => null,
        ]);

        // Mock ModService to return files for both mods
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturnCallback(function ($modId, $version, $software, $source) {
                return [
                    $source === 'curseforge' ? ['id' => 1, 'displayName' => 'Test Mod 1.0.0'] : ['id' => 'v1', 'version_number' => '1.0.0'],
                ];
            });

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent (all mods compatible)
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class
        );
    }

    /**
     * Test that command skips items without platform metadata (lines 62-66).
     */
    public function test_command_skips_items_without_platform_metadata(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        // Create item without curseforge_mod_id or modrinth_project_id (lines 64-66)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod Without Platform',
            'curseforge_mod_id' => null,
            'modrinth_project_id' => null,
            'source' => null,
        ]);

        // Create a valid item to ensure the modpack can still be processed
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod Valid',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return files for the valid mod
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturn([
                ['id' => 1, 'displayName' => 'Test Mod Valid 1.0.0'],
            ]);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent (all valid mods are compatible)
        Notification::assertSentTo(
            $user,
            MinecraftVersionUpdateAvailable::class
        );
    }

    /**
     * Test that command skips items when modId is null (line 72).
     */
    public function test_command_skips_items_when_mod_id_is_null(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'minecraft_update_reminder_version' => '1.21.1',
            'minecraft_update_reminder_software' => 'fabric',
        ]);

        // Create item with source but null modId (line 72)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'curseforge_mod_id' => null,
            'modrinth_project_id' => null,
            'source' => 'curseforge', // Source is set but modId is null
        ]);

        // Create a valid item to ensure the modpack can still be processed
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod Valid',
            'curseforge_mod_id' => 123456,
            'source' => 'curseforge',
        ]);

        // Mock ModService to return files for the valid mod
        $modServiceMock = $this->createMock(ModService::class);
        $modServiceMock->method('getModFiles')
            ->willReturn([
                ['id' => 1, 'displayName' => 'Test Mod Valid 1.0.0'],
            ]);

        $this->app->instance(ModService::class, $modServiceMock);

        Notification::fake();

        $this->artisan('minecraft:check-version-updates')
            ->assertSuccessful();

        // Verify notification was sent (all valid mods are compatible)
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
