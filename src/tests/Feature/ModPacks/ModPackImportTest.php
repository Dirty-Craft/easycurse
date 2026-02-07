<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use App\Services\ModService;
use Mockery;
use Tests\TestCase;

class ModPackImportTest extends TestCase
{
    /**
     * Test that user can import identified mods with version matching.
     */
    public function test_user_can_import_identified_mods_with_version_matching(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.21',
            'software' => 'fabric',
        ]);

        // Mock ModService to return compatible versions
        $mockModService = Mockery::mock(ModService::class);
        $mockModService->shouldReceive('getMod')
            ->with(123, 'curseforge')
            ->andReturn([
                'id' => 123,
                'name' => 'Test Mod',
                'slug' => 'test-mod',
            ]);

        $mockModService->shouldReceive('getModFiles')
            ->with(123, '1.21', 'fabric', 'curseforge')
            ->andReturn([
                [
                    'id' => 456,
                    'displayName' => 'Test Mod 1.21-1.0.0',
                    'fileName' => 'testmod-1.21-1.0.0.jar',
                ],
            ]);

        $this->app->instance(ModService::class, $mockModService);

        $response = $this->actingAs($user)->postJson("/mod-packs/{$modPack->id}/import-identified-mods", [
            'mods' => [
                [
                    'source' => 'curseforge',
                    'mod_id' => 123,
                    'file_id' => 999, // Original file ID from uploaded mod
                    'display_name' => 'Test Mod 1.20-0.9.0', // Original version
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Verify the mod was added with the compatible version
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => 'Test Mod 1.21-1.0.0', // Should use compatible version
            'curseforge_mod_id' => 123,
            'curseforge_file_id' => 456, // Should use compatible file ID
        ]);
    }

    /**
     * Test that import skips mods already in the pack.
     */
    public function test_import_skips_duplicate_mods(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.21',
            'software' => 'fabric',
        ]);

        // Add a mod to the pack
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 123,
        ]);

        $response = $this->actingAs($user)->postJson("/mod-packs/{$modPack->id}/import-identified-mods", [
            'mods' => [
                [
                    'source' => 'curseforge',
                    'mod_id' => 123,
                    'file_id' => 456,
                    'display_name' => 'Test Mod',
                ],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['results']['skipped']);
        $this->assertEquals('Already in mod pack', $data['results']['skipped'][0]['reason']);
    }

    /**
     * Test that import fails gracefully when no compatible version exists.
     */
    public function test_import_fails_when_no_compatible_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.21',
            'software' => 'fabric',
        ]);

        // Mock ModService to return no compatible versions
        $mockModService = Mockery::mock(ModService::class);
        $mockModService->shouldReceive('getMod')
            ->with(123, 'curseforge')
            ->andReturn([
                'id' => 123,
                'name' => 'Test Mod',
                'slug' => 'test-mod',
            ]);

        $mockModService->shouldReceive('getModFiles')
            ->with(123, '1.21', 'fabric', 'curseforge')
            ->andReturn([]); // No compatible versions

        $this->app->instance(ModService::class, $mockModService);

        $response = $this->actingAs($user)->postJson("/mod-packs/{$modPack->id}/import-identified-mods", [
            'mods' => [
                [
                    'source' => 'curseforge',
                    'mod_id' => 123,
                    'file_id' => 456,
                    'display_name' => 'Test Mod',
                ],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['results']['failed']);
        $this->assertStringContainsString('No compatible version found', $data['results']['failed'][0]['reason']);
    }

    /**
     * Test that import requires authentication.
     */
    public function test_import_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->postJson("/mod-packs/{$modPack->id}/import-identified-mods", [
            'mods' => [],
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test that user cannot import to other user's mod pack.
     */
    public function test_user_cannot_import_to_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->postJson("/mod-packs/{$modPack->id}/import-identified-mods", [
            'mods' => [],
        ]);

        $response->assertNotFound();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
