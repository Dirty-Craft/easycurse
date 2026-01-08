<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackChangeVersionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can change version of their mod pack.
     */
    public function test_user_can_change_version_of_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'Original description',
        ]);

        // Create mod items with CurseForge IDs
        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'jei',
            'sort_order' => 1,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'OptiFine',
            'curseforge_mod_id' => 234567,
            'curseforge_file_id' => 890123,
            'curseforge_slug' => 'optifine',
            'sort_order' => 2,
        ]);

        // Mock CurseForge API responses for checking if mods have versions for 1.21.0
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 999001,
                        'displayName' => 'JEI 1.21.0-12.0.0.1016',
                        'fileName' => 'jei-1.21.0-12.0.0.1016.jar',
                        'fileDate' => '2024-02-01T00:00:00Z',
                        'fileLength' => 1025000,
                        'gameVersions' => ['1.21.0'],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/234567/files*' => Http::response([
                'data' => [
                    [
                        'id' => 999002,
                        'displayName' => 'OptiFine 1.21.0-HD-U-I1',
                        'fileName' => 'optifine-1.21.0-HD-U-I1.jar',
                        'fileDate' => '2024-02-02T00:00:00Z',
                        'fileLength' => 1026000,
                        'gameVersions' => ['1.21.0'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        // Should redirect to the new mod pack
        $response->assertRedirect();

        // Verify new mod pack was created with correct name
        $this->assertDatabaseHas('mod_packs', [
            'user_id' => $user->id,
            'name' => 'My Mod Pack (Updated to 1.21.0 Fabric)',
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
            'description' => 'Original description',
        ]);

        // Verify original mod pack still exists
        $this->assertDatabaseHas('mod_packs', [
            'id' => $modPack->id,
            'name' => 'My Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Get the new mod pack
        $newModPack = ModPack::where('name', 'My Mod Pack (Updated to 1.21.0 Fabric)')->first();

        // Verify mod items were copied with new versions
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $newModPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 999001, // New file ID
            'curseforge_slug' => 'jei',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $newModPack->id,
            'mod_name' => 'OptiFine',
            'curseforge_mod_id' => 234567,
            'curseforge_file_id' => 999002, // New file ID
            'curseforge_slug' => 'optifine',
            'sort_order' => 2,
        ]);

        // Verify original items still exist
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $item1->id,
            'mod_pack_id' => $modPack->id,
        ]);

        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $item2->id,
            'mod_pack_id' => $modPack->id,
        ]);
    }

    /**
     * Test that changing version fails when mods don't have matching versions.
     */
    public function test_changing_version_fails_when_mods_dont_have_matching_versions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create mod items with CurseForge IDs
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'jei',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'OptiFine',
            'curseforge_mod_id' => 234567,
            'curseforge_file_id' => 890123,
            'curseforge_slug' => 'optifine',
        ]);

        // Mock CurseForge API responses - JEI has version, OptiFine doesn't
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 999001,
                        'displayName' => 'JEI 1.21.0-12.0.0.1016',
                        'fileName' => 'jei-1.21.0-12.0.0.1016.jar',
                        'fileDate' => '2024-02-01T00:00:00Z',
                        'fileLength' => 1025000,
                        'gameVersions' => ['1.21.0'],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/234567/files*' => Http::response([
                'data' => [], // No files for this version
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        // Should redirect back with errors
        $response->assertRedirect();
        $response->assertSessionHasErrors('version_change');

        // Verify no new mod pack was created
        $this->assertDatabaseMissing('mod_packs', [
            'name' => 'My Mod Pack (Updated to 1.21.0 Fabric)',
        ]);

        // Verify error message mentions the mod without version
        $errors = $response->getSession()->get('errors');
        $this->assertNotNull($errors);
        $versionChangeError = $errors->get('version_change');
        $this->assertNotEmpty($versionChangeError);
        $this->assertStringContainsString('OptiFine', $versionChangeError[0]);
        $this->assertStringContainsString('1.21.0', $versionChangeError[0]);
    }

    /**
     * Test that changing version requires authentication.
     */
    public function test_changing_version_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot change version of other user's mod pack.
     */
    public function test_user_cannot_change_version_of_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that changing version requires minecraft_version.
     */
    public function test_changing_version_requires_minecraft_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'software' => 'fabric',
        ]);

        $response->assertSessionHasErrors('minecraft_version');
    }

    /**
     * Test that changing version requires software.
     */
    public function test_changing_version_requires_software(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that changing version validates software enum.
     */
    public function test_changing_version_validates_software_enum(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'invalid',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that changing version accepts all valid software types.
     */
    public function test_changing_version_accepts_all_valid_software_types(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create a mod item without CurseForge ID (should be copied as-is)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'curseforge_mod_id' => null,
        ]);

        $validSoftwareTypes = ['forge', 'fabric', 'quilt', 'neoforge'];

        foreach ($validSoftwareTypes as $software) {
            Http::fake([
                'api.curseforge.com/v1/*' => Http::response(['data' => []], 200),
            ]);

            $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
                'minecraft_version' => '1.21.0',
                'software' => $software,
            ]);

            // Should succeed (no mods with CurseForge IDs to validate)
            $response->assertRedirect();
        }
    }

    /**
     * Test that changing to same version and software redirects without creating new pack.
     */
    public function test_changing_to_same_version_and_software_redirects_without_creating_new_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Should redirect to the same mod pack
        $response->assertRedirect("/mod-packs/{$modPack->id}");

        // Verify no new mod pack was created
        $this->assertEquals(1, ModPack::where('user_id', $user->id)->count());
    }

    /**
     * Test that changing version with empty mod pack creates new pack.
     */
    public function test_changing_version_with_empty_mod_pack_creates_new_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Empty Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // No mod items

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        $response->assertRedirect();

        // Verify new mod pack was created
        $this->assertDatabaseHas('mod_packs', [
            'user_id' => $user->id,
            'name' => 'Empty Mod Pack (Updated to 1.21.0 Fabric)',
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        // Verify new mod pack has no items
        $newModPack = ModPack::where('name', 'Empty Mod Pack (Updated to 1.21.0 Fabric)')->first();
        $this->assertEquals(0, $newModPack->items()->count());
    }
}
