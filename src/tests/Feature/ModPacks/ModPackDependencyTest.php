<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackDependencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that dependencies are automatically added when a mod is added.
     */
    public function test_dependencies_are_automatically_added_when_adding_mod(): void
    {
        // Clear cache to ensure mocks are used
        Cache::flush();

        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Mock CurseForge API responses
        Http::fake([
            // Main mod file with dependencies
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'displayName' => '1.20.1-1.0.0',
                    'fileName' => 'main-mod-1.20.1-1.0.0.jar',
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
            // Dependency mod details
            'api.curseforge.com/v1/mods/987654' => Http::response([
                'data' => [
                    'id' => 987654,
                    'name' => 'Required Dependency',
                    'slug' => 'required-dependency',
                ],
            ], 200),
            // Dependency mod files
            'api.curseforge.com/v1/mods/987654/files*' => Http::response([
                'data' => [
                    [
                        'id' => 555555,
                        'displayName' => '1.20.1-2.0.0',
                        'fileName' => 'required-dependency-1.20.1-2.0.0.jar',
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
            // Dependency mod file
            'api.curseforge.com/v1/mods/987654/files/555555*' => Http::response([
                'data' => [
                    'id' => 555555,
                    'displayName' => '1.20.1-2.0.0',
                    'fileName' => 'required-dependency-1.20.1-2.0.0.jar',
                    'dependencies' => [], // No sub-dependencies
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Main Mod',
            'mod_version' => '1.20.1-1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'main-mod',
            'source' => 'curseforge',
        ]);

        $response->assertRedirect();

        // Verify main mod was added (not auto-added)
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'curseforge_mod_id' => 123456,
            'is_auto_added' => false,
        ]);

        // Verify dependency was automatically added
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Required Dependency',
            'curseforge_mod_id' => 987654,
            'is_auto_added' => true,
        ]);
    }

    /**
     * Test that removing a mod prevents removal if it's required by other mods.
     */
    public function test_cannot_remove_mod_required_by_others(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create a dependency mod
        $dependency = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Required Dependency',
            'curseforge_mod_id' => 987654,
            'curseforge_file_id' => 555555,
            'source' => 'curseforge',
        ]);

        // Create a mod that requires the dependency
        $mainMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'source' => 'curseforge',
        ]);

        // Mock API to return dependency information showing main mod requires dependency
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Attempt to remove the dependency
        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}/items/{$dependency->id}");

        $response->assertRedirect("/mod-packs/{$modPack->id}");
        $response->assertSessionHas('error');

        // Verify dependency was NOT removed
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $dependency->id,
        ]);
    }

    /**
     * Test that orphaned auto-added mods are cleaned up when a mod is removed.
     */
    public function test_orphaned_auto_added_mods_are_cleaned_up(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create main mod
        $mainMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'source' => 'curseforge',
            'is_auto_added' => false,
        ]);

        // Create auto-added dependency that only the main mod requires
        $dependency = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Required Dependency',
            'curseforge_mod_id' => 987654,
            'curseforge_file_id' => 555555,
            'source' => 'curseforge',
            'is_auto_added' => true,
        ]);

        // Mock API to show dependency is NOT required by any other mod
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Remove the main mod
        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}/items/{$mainMod->id}");

        $response->assertRedirect("/mod-packs/{$modPack->id}");

        // Verify main mod was removed
        $this->assertDatabaseMissing('mod_pack_items', [
            'id' => $mainMod->id,
        ]);

        // Verify orphaned dependency was also removed
        $this->assertDatabaseMissing('mod_pack_items', [
            'id' => $dependency->id,
        ]);
    }

    /**
     * Test that auto-added mods are not cleaned up if still required.
     */
    public function test_auto_added_mods_not_cleaned_up_if_still_required(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create two main mods
        $mainMod1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod 1',
            'curseforge_mod_id' => 111111,
            'curseforge_file_id' => 222222,
            'source' => 'curseforge',
            'is_auto_added' => false,
        ]);

        $mainMod2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod 2',
            'curseforge_mod_id' => 333333,
            'curseforge_file_id' => 444444,
            'source' => 'curseforge',
            'is_auto_added' => false,
        ]);

        // Create auto-added dependency required by both mods
        $dependency = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Required Dependency',
            'curseforge_mod_id' => 987654,
            'curseforge_file_id' => 555555,
            'source' => 'curseforge',
            'is_auto_added' => true,
        ]);

        // Mock API to show dependency is required by remaining mod
        Http::fake([
            'api.curseforge.com/v1/mods/333333/files/444444*' => Http::response([
                'data' => [
                    'id' => 444444,
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Remove only the first main mod
        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}/items/{$mainMod1->id}");

        $response->assertRedirect("/mod-packs/{$modPack->id}");

        // Verify first main mod was removed
        $this->assertDatabaseMissing('mod_pack_items', [
            'id' => $mainMod1->id,
        ]);

        // Verify dependency was NOT removed (still required by mainMod2)
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $dependency->id,
        ]);
    }

    /**
     * Test that dependency endpoint returns dependency tree and conflicts.
     */
    public function test_dependency_endpoint_returns_tree_and_conflicts(): void
    {
        // Clear cache to ensure mocks are used
        Cache::flush();

        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create an existing mod that conflicts with the new mod
        $existingMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Conflicting Mod',
            'curseforge_mod_id' => 999999,
            'curseforge_file_id' => 888888,
            'source' => 'curseforge',
        ]);

        // Mock API responses
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'displayName' => '1.20.1-1.0.0',
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                        [
                            'modId' => 999999,
                            'relationType' => 4, // Incompatible
                        ],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/987654' => Http::response([
                'data' => [
                    'id' => 987654,
                    'name' => 'Required Dependency',
                    'slug' => 'required-dependency',
                ],
            ], 200),
            'api.curseforge.com/v1/mods/987654/files*' => Http::response([
                'data' => [
                    [
                        'id' => 555555,
                        'displayName' => '1.20.1-2.0.0',
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/987654/files/555555*' => Http::response([
                'data' => [
                    'id' => 555555,
                    'displayName' => '1.20.1-2.0.0',
                    'dependencies' => [],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/999999/files/888888*' => Http::response([
                'data' => [
                    'id' => 888888,
                    'displayName' => '1.20.1-1.0.0',
                    'dependencies' => [],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=123456&file_id=789012&source=curseforge");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tree' => [
                'mod_id',
                'file_id',
                'source',
                'dependencies' => [
                    'required',
                    'optional',
                    'embedded',
                    'incompatible',
                ],
            ],
            'conflicts',
        ]);

        $data = $response->json();
        // The conflicts should contain the existing mod since the new mod declares it as incompatible
        $this->assertNotEmpty($data['conflicts'], 'Conflicts should be detected when new mod declares existing mod as incompatible');
        $this->assertCount(1, $data['conflicts']);
        $this->assertEquals('Conflicting Mod', $data['conflicts'][0]['existing_mod_name']);
    }

    /**
     * Test that bulk delete prevents removal if mods are required.
     */
    public function test_bulk_delete_prevents_removal_if_mods_required(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create dependency mod
        $dependency = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Required Dependency',
            'curseforge_mod_id' => 987654,
            'curseforge_file_id' => 555555,
            'source' => 'curseforge',
        ]);

        // Create mod that requires the dependency
        $mainMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'source' => 'curseforge',
        ]);

        // Create another mod that can be deleted
        $otherMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Other Mod',
            'curseforge_mod_id' => 111111,
            'curseforge_file_id' => 222222,
            'source' => 'curseforge',
        ]);

        // Mock API to show main mod requires dependency
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Attempt to bulk delete dependency and other mod
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-items/delete", [
            'item_ids' => [$dependency->id, $otherMod->id],
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);

        // Verify dependency was NOT removed
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $dependency->id,
        ]);

        // Verify other mod was NOT removed (bulk operation fails entirely)
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $otherMod->id,
        ]);
    }

    /**
     * Test that dependencies are added when updating a mod version.
     */
    public function test_dependencies_are_added_when_updating_mod_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'mod_version' => '1.20.1-1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'main-mod',
            'source' => 'curseforge',
        ]);

        // Mock API responses for new version
        Http::fake([
            'api.curseforge.com/v1/mods/123456' => Http::response([
                'data' => [
                    'id' => 123456,
                    'name' => 'Main Mod',
                    'slug' => 'main-mod',
                ],
            ], 200),
            'api.curseforge.com/v1/mods/123456/files/999999*' => Http::response([
                'data' => [
                    'id' => 999999,
                    'displayName' => '1.20.1-2.0.0',
                    'fileName' => 'main-mod-1.20.1-2.0.0.jar',
                    'dependencies' => [
                        [
                            'modId' => 987654,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
            // Mock dependency mod
            'api.curseforge.com/v1/mods/987654' => Http::response([
                'data' => [
                    'id' => 987654,
                    'name' => 'Required Dependency',
                    'slug' => 'required-dependency',
                ],
            ], 200),
            'api.curseforge.com/v1/mods/987654/files*' => Http::response([
                'data' => [
                    [
                        'id' => 555555,
                        'displayName' => '1.20.1-2.0.0',
                        'fileName' => 'required-dependency-1.20.1-2.0.0.jar',
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/987654/files/555555*' => Http::response([
                'data' => [
                    'id' => 555555,
                    'displayName' => '1.20.1-2.0.0',
                    'dependencies' => [],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'Main Mod',
            'mod_version' => '1.20.1-2.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 999999,
            'curseforge_slug' => 'main-mod',
            'source' => 'curseforge',
        ]);

        $response->assertRedirect();

        // Verify dependency was automatically added
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Required Dependency',
            'curseforge_mod_id' => 987654,
            'is_auto_added' => true,
        ]);
    }
}
