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
     * Test that dependency endpoint returns tree for Modrinth source.
     * Covers DependencyResolutionService and ModrinthService getVersionDependencies.
     */
    public function test_dependency_endpoint_returns_tree_for_modrinth_source(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.modrinth.com/v2/version/main-version-id*' => Http::response([
                'id' => 'main-version-id',
                'project_id' => 'main-mod',
                'version_number' => '1.0.0',
                'dependencies' => [
                    ['project_id' => 'fabric-api', 'dependency_type' => 'required'],
                ],
                'game_versions' => ['1.20.1'],
                'loaders' => ['fabric'],
                'files' => [['url' => 'https://cdn.example.com/main.jar', 'filename' => 'main.jar']],
            ], 200),
            'api.modrinth.com/v2/project/fabric-api/version*' => Http::response([
                [
                    'id' => 'dep-version-id',
                    'project_id' => 'fabric-api',
                    'version_number' => '0.91.0',
                    'dependencies' => [],
                    'game_versions' => ['1.20.1'],
                    'loaders' => ['fabric'],
                    'files' => [['url' => 'https://cdn.example.com/fabric-api.jar', 'filename' => 'fabric-api.jar']],
                ],
            ], 200),
            'api.modrinth.com/v2/version/dep-version-id*' => Http::response([
                'id' => 'dep-version-id',
                'project_id' => 'fabric-api',
                'version_number' => '0.91.0',
                'dependencies' => [],
                'game_versions' => ['1.20.1'],
                'loaders' => ['fabric'],
                'files' => [['url' => 'https://cdn.example.com/fabric-api.jar', 'filename' => 'fabric-api.jar']],
            ], 200),
            'api.modrinth.com/v2/project/main-mod*' => Http::response([
                'id' => 'main-mod',
                'slug' => 'main-mod',
                'title' => 'Main Mod',
            ], 200),
            'api.modrinth.com/v2/project/fabric-api*' => Http::response([
                'id' => 'fabric-api',
                'slug' => 'fabric-api',
                'title' => 'Fabric API',
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=main-mod&file_id=main-version-id&source=modrinth");

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
        $this->assertEquals('main-mod', $data['tree']['mod_id']);
        $this->assertEquals('main-version-id', $data['tree']['file_id']);
        $this->assertEquals('modrinth', $data['tree']['source']);
        $this->assertCount(1, $data['tree']['dependencies']['required']);
        $this->assertEquals('fabric-api', $data['tree']['dependencies']['required'][0]['mod_id']);
    }

    /**
     * Test that dependencies are automatically added when adding a Modrinth mod.
     * Covers ModPackController addRequiredDependencies with modrinth and ModrinthService.
     */
    public function test_dependencies_are_automatically_added_when_adding_modrinth_mod(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.modrinth.com/v2/version/main-mod-version-id*' => Http::response([
                'id' => 'main-mod-version-id',
                'project_id' => 'main-mod',
                'version_number' => '1.0.0',
                'dependencies' => [
                    ['project_id' => 'fabric-api', 'dependency_type' => 'required'],
                ],
                'game_versions' => ['1.20.1'],
                'loaders' => ['fabric'],
                'files' => [['url' => 'https://cdn.example.com/main.jar', 'filename' => 'main.jar']],
            ], 200),
            'api.modrinth.com/v2/version/fabric-api-version-id*' => Http::response([
                'id' => 'fabric-api-version-id',
                'project_id' => 'fabric-api',
                'version_number' => '0.91.0',
                'dependencies' => [],
                'game_versions' => ['1.20.1'],
                'loaders' => ['fabric'],
                'files' => [['url' => 'https://cdn.example.com/fabric-api.jar', 'filename' => 'fabric-api.jar']],
            ], 200),
            'api.modrinth.com/v2/project/main-mod/version*' => Http::response([
                [
                    'id' => 'main-mod-version-id',
                    'project_id' => 'main-mod',
                    'version_number' => '1.0.0',
                    'dependencies' => [
                        ['project_id' => 'fabric-api', 'dependency_type' => 'required'],
                    ],
                    'game_versions' => ['1.20.1'],
                    'loaders' => ['fabric'],
                    'files' => [['url' => 'https://cdn.example.com/main.jar', 'filename' => 'main.jar']],
                ],
            ], 200),
            'api.modrinth.com/v2/project/fabric-api/version*' => Http::response([
                [
                    'id' => 'fabric-api-version-id',
                    'project_id' => 'fabric-api',
                    'version_number' => '0.91.0',
                    'dependencies' => [],
                    'game_versions' => ['1.20.1'],
                    'loaders' => ['fabric'],
                    'files' => [['url' => 'https://cdn.example.com/fabric-api.jar', 'filename' => 'fabric-api.jar']],
                ],
            ], 200),
            'api.modrinth.com/v2/project/main-mod*' => Http::response([
                'id' => 'main-mod',
                'slug' => 'main-mod',
                'title' => 'Main Mod',
            ], 200),
            'api.modrinth.com/v2/project/fabric-api*' => Http::response([
                'id' => 'fabric-api',
                'slug' => 'fabric-api',
                'title' => 'Fabric API',
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Main Mod',
            'mod_version' => '1.0.0',
            'modrinth_project_id' => 'main-mod',
            'modrinth_version_id' => 'main-mod-version-id',
            'modrinth_slug' => 'main-mod',
            'source' => 'modrinth',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'modrinth_project_id' => 'main-mod',
            'is_auto_added' => false,
        ]);

        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Fabric API',
            'modrinth_project_id' => 'fabric-api',
            'is_auto_added' => true,
        ]);
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

    /**
     * Test that dependency resolution handles cycles gracefully.
     * Covers DependencyResolutionService cycle prevention logic.
     */
    public function test_dependency_resolution_handles_cycles(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Mock circular dependency: Mod A depends on Mod B, Mod B depends on Mod A
        Http::fake([
            'api.curseforge.com/v1/mods/111111/files/222222*' => Http::response([
                'data' => [
                    'id' => 222222,
                    'displayName' => '1.20.1-1.0.0',
                    'dependencies' => [
                        [
                            'modId' => 333333,
                            'relationType' => 3, // RequiredDependency
                        ],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/333333/files/444444*' => Http::response([
                'data' => [
                    'id' => 444444,
                    'displayName' => '1.20.1-1.0.0',
                    'dependencies' => [
                        [
                            'modId' => 111111,
                            'relationType' => 3, // RequiredDependency (circular)
                        ],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/333333' => Http::response([
                'data' => [
                    'id' => 333333,
                    'name' => 'Mod B',
                    'slug' => 'mod-b',
                ],
            ], 200),
            'api.curseforge.com/v1/mods/333333/files*' => Http::response([
                'data' => [
                    [
                        'id' => 444444,
                        'displayName' => '1.20.1-1.0.0',
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=111111&file_id=222222&source=curseforge");

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

        // Should handle the cycle gracefully without infinite recursion
        $data = $response->json();
        $this->assertEquals('111111', $data['tree']['mod_id']);
    }

    /**
     * Test that validateModPackDependencies method works correctly.
     * Covers DependencyResolutionService validateModPackDependencies method.
     */
    public function test_validate_mod_pack_dependencies(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create a mod that has a missing dependency
        $mainMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Main Mod',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'source' => 'curseforge',
        ]);

        // Mock API to show main mod requires a dependency that's not in the mod pack
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

        // Test the validateModPackDependencies method directly
        $dependencyService = new \App\Services\DependencyResolutionService;
        $missingDependencies = $dependencyService->validateModPackDependencies($modPack);

        $this->assertNotEmpty($missingDependencies);
        $this->assertEquals(123456, $missingDependencies[0]['required_by_mod_id']);
        $this->assertEquals('Main Mod', $missingDependencies[0]['required_by_mod_name']);
        $this->assertEquals(987654, $missingDependencies[0]['missing_mod_id']);
        $this->assertEquals('curseforge', $missingDependencies[0]['source']);
    }

    /**
     * Test that checkConflicts method detects reverse conflicts.
     * Covers DependencyResolutionService checkConflicts reverse checking logic.
     */
    public function test_check_conflicts_detects_reverse_conflicts(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create an existing mod that declares the new mod as incompatible
        $existingMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Existing Mod',
            'curseforge_mod_id' => 999999,
            'curseforge_file_id' => 888888,
            'source' => 'curseforge',
        ]);

        // Mock API to show existing mod declares new mod as incompatible
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'dependencies' => [], // New mod has no conflicts
                ],
            ], 200),
            'api.curseforge.com/v1/mods/999999/files/888888*' => Http::response([
                'data' => [
                    'id' => 888888,
                    'dependencies' => [
                        [
                            'modId' => 123456,
                            'relationType' => 4, // Incompatible with new mod
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Test the checkConflicts method directly
        $dependencyService = new \App\Services\DependencyResolutionService;
        $conflicts = $dependencyService->checkConflicts(123456, 789012, 'curseforge', $modPack);

        $this->assertNotEmpty($conflicts);
        $this->assertEquals('incompatible', $conflicts[0]['type']);
        $this->assertEquals(999999, $conflicts[0]['mod_id']);
        $this->assertEquals('Existing Mod', $conflicts[0]['existing_mod_name']);
        $this->assertEquals('curseforge', $conflicts[0]['source']);
    }

    /**
     * Test that checkConflicts handles items without file IDs gracefully.
     * Covers DependencyResolutionService checkConflicts with missing file IDs.
     */
    public function test_check_conflicts_handles_items_without_file_ids(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create an existing mod without file ID
        $existingMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Existing Mod',
            'curseforge_mod_id' => 999999,
            'curseforge_file_id' => null, // No file ID
            'source' => 'curseforge',
        ]);

        // Mock API for new mod
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'dependencies' => [],
                ],
            ], 200),
        ]);

        // Test the checkConflicts method directly
        $dependencyService = new \App\Services\DependencyResolutionService;
        $conflicts = $dependencyService->checkConflicts(123456, 789012, 'curseforge', $modPack);

        // Should not find conflicts since existing mod has no file ID
        $this->assertEmpty($conflicts);
    }

    /**
     * Test that checkConflicts handles Modrinth conflicts correctly.
     * Covers DependencyResolutionService checkConflicts with Modrinth mods.
     */
    public function test_check_conflicts_handles_modrinth_conflicts(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Create an existing Modrinth mod
        $existingMod = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Existing Modrinth Mod',
            'modrinth_project_id' => 'existing-mod',
            'modrinth_version_id' => 'existing-version',
            'source' => 'modrinth',
        ]);

        // Mock API to show new mod declares existing mod as incompatible
        Http::fake([
            'api.modrinth.com/v2/version/new-version*' => Http::response([
                'id' => 'new-version',
                'dependencies' => [
                    [
                        'project_id' => 'existing-mod',
                        'dependency_type' => 'incompatible',
                    ],
                ],
            ], 200),
        ]);

        // Test the checkConflicts method directly
        $dependencyService = new \App\Services\DependencyResolutionService;
        $conflicts = $dependencyService->checkConflicts('new-mod', 'new-version', 'modrinth', $modPack);

        $this->assertNotEmpty($conflicts);
        $this->assertEquals('incompatible', $conflicts[0]['type']);
        $this->assertEquals('existing-mod', $conflicts[0]['mod_id']);
        $this->assertEquals('Existing Modrinth Mod', $conflicts[0]['existing_mod_name']);
        $this->assertEquals('modrinth', $conflicts[0]['source']);
    }
}
