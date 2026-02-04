<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackAdvancedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can search for mods with query string.
     */
    public function test_user_can_search_for_mods_with_query(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Mock CurseForge API response
        Http::fake([
            'api.curseforge.com/v1/mods/search*' => Http::response([
                'data' => [
                    [
                        'id' => 238222,
                        'name' => 'Just Enough Items (JEI)',
                        'slug' => 'jei',
                        'summary' => 'JEI is an item and recipe viewing mod for Minecraft',
                        'downloadCount' => 100000000,
                        'dateModified' => '2024-01-01T00:00:00Z',
                        'gameVersionLatestFiles' => [
                            [
                                'gameVersion' => '1.20.1',
                                'projectFileId' => 4638256,
                                'projectFileName' => 'jei-1.20.1-11.6.0.1015.jar',
                                'fileType' => 1,
                            ],
                        ],
                        'logo' => [
                            'thumbnailUrl' => 'https://media.forgecdn.net/avatars/thumbnails/238/222/256/256/637618359877649897.png',
                        ],
                    ],
                ],
                'pagination' => [
                    'index' => 0,
                    'pageSize' => 20,
                    'resultCount' => 1,
                    'totalCount' => 1,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=JEI");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    '_source',
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data); // Just check that we got some results
    }

    /**
     * Test that searching mods requires authentication.
     */
    public function test_searching_mods_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/search-mods?query=JEI");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot search mods for other user's mod pack.
     */
    public function test_user_cannot_search_mods_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=JEI");

        $response->assertNotFound();
    }

    /**
     * Test that searching mods validates query parameter.
     */
    public function test_searching_mods_validates_query_parameter(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Test missing query
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods");
        $response->assertSessionHasErrors('query');

        // Test query too short
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=a");
        $response->assertSessionHasErrors('query');

        // Test query too long
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".str_repeat('a', 256));
        $response->assertSessionHasErrors('query');
    }

    /**
     * Test that user can search mods with URL.
     */
    public function test_user_can_search_mods_with_url(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Mock CurseForge API response for mod by ID
        Http::fake([
            'api.curseforge.com/v1/mods/238222' => Http::response([
                'data' => [
                    'id' => 238222,
                    'name' => 'Just Enough Items (JEI)',
                    'slug' => 'jei',
                    'summary' => 'JEI is an item and recipe viewing mod for Minecraft',
                    '_source' => 'curseforge',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=https://www.curseforge.com/minecraft/mc-mods/jei");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    '_source',
                ],
            ],
        ]);
    }

    /**
     * Test that user can search mods with Modrinth URL.
     */
    public function test_user_can_search_mods_with_modrinth_url(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Mock Modrinth API response for mod by ID
        Http::fake([
            'api.modrinth.com/v2/project/AANobbMI' => Http::response([
                'id' => 'AANobbMI',
                'title' => 'Sodium',
                'slug' => 'sodium',
                'description' => 'A modern rendering engine for Minecraft',
                '_source' => 'modrinth',
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=https://modrinth.com/mod/sodium");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    '_source',
                ],
            ],
        ]);
    }

    /**
     * Test that user can search mods with slug.
     */
    public function test_user_can_search_mods_with_slug(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Mock ModService searchModBySlug response
        Http::fake([
            'api.curseforge.com/v1/mods/search*' => Http::response([
                'data' => [
                    [
                        'id' => 238222,
                        'name' => 'Just Enough Items (JEI)',
                        'slug' => 'jei',
                        '_source' => 'curseforge',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=jei");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test that user can get mod files for a specific mod.
     */
    public function test_user_can_get_mod_files(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Mock CurseForge API response
        Http::fake([
            'api.curseforge.com/v1/mods/238222/files*' => Http::response([
                'data' => [
                    [
                        'id' => 4638256,
                        'displayName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1000000,
                        'downloadCount' => 50000,
                        'gameVersions' => ['1.20.1'],
                        'dependencies' => [],
                    ],
                ],
                'pagination' => [
                    'index' => 0,
                    'pageSize' => 50,
                    'resultCount' => 1,
                    'totalCount' => 1,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=238222&source=curseforge");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'displayName',
                    'fileName',
                    'fileDate',
                    'fileLength',
                    'downloadCount',
                    'gameVersions',
                ],
            ],
        ]);
    }

    /**
     * Test that getting mod files requires authentication.
     */
    public function test_getting_mod_files_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/mod-files?mod_id=238222");

        $response->assertRedirect('/login');
    }

    /**
     * Test that getting mod files validates parameters.
     */
    public function test_getting_mod_files_validates_parameters(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Test missing mod_id
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files");
        $response->assertSessionHasErrors('mod_id');

        // Test invalid source
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=238222&source=invalid");
        $response->assertSessionHasErrors('source');
    }

    /**
     * Test that user can get mod files for Modrinth source.
     */
    public function test_user_can_get_mod_files_for_modrinth(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Mock Modrinth API response
        Http::fake([
            'api.modrinth.com/v2/project/AANobbMI/version*' => Http::response([
                [
                    'id' => 'version123',
                    'name' => 'Sodium 0.5.3',
                    'version_number' => '0.5.3',
                    'date_published' => '2024-01-01T00:00:00Z',
                    'downloads' => 50000,
                    'game_versions' => ['1.20.1'],
                    'loaders' => ['fabric'],
                    'files' => [
                        [
                            'filename' => 'sodium-fabric-0.5.3+mc1.20.1.jar',
                            'size' => 1000000,
                            'url' => 'https://cdn.modrinth.com/data/AANobbMI/versions/version123/sodium-fabric-0.5.3+mc1.20.1.jar',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=AANobbMI&source=modrinth");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'version_number',
                    'date_published',
                    'downloads',
                    'game_versions',
                ],
            ],
        ]);
    }

    /**
     * Test that user can get mod dependencies.
     */
    public function test_user_can_get_mod_dependencies(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Mock dependency service responses
        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256' => Http::response([
                'data' => [
                    'id' => 4638256,
                    'dependencies' => [
                        [
                            'modId' => 32274,
                            'relationType' => 3, // Required
                        ],
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/32274' => Http::response([
                'data' => [
                    'id' => 32274,
                    'name' => 'Minecraft Forge',
                    'slug' => 'forge',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=238222&file_id=4638256&source=curseforge");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tree',
            'conflicts',
        ]);
    }

    /**
     * Test that getting mod dependencies requires authentication.
     */
    public function test_getting_mod_dependencies_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=238222&file_id=4638256&source=curseforge");

        $response->assertRedirect('/login');
    }

    /**
     * Test that getting mod dependencies validates parameters.
     */
    public function test_getting_mod_dependencies_validates_parameters(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Test missing required parameters
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies");
        $response->assertSessionHasErrors(['mod_id', 'file_id', 'source']);

        // Test invalid source
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=238222&file_id=4638256&source=invalid");
        $response->assertSessionHasErrors('source');
    }

    /**
     * Test that user can get mod dependencies for Modrinth source.
     */
    public function test_user_can_get_mod_dependencies_for_modrinth(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Mock dependency service responses
        Http::fake([
            'api.modrinth.com/v2/version/version123' => Http::response([
                'id' => 'version123',
                'dependencies' => [
                    [
                        'project_id' => 'P7dR8mSH',
                        'dependency_type' => 'required',
                    ],
                ],
            ], 200),
            'api.modrinth.com/v2/project/P7dR8mSH' => Http::response([
                'id' => 'P7dR8mSH',
                'title' => 'Fabric API',
                'slug' => 'fabric-api',
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-dependencies?mod_id=AANobbMI&file_id=version123&source=modrinth");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tree',
            'conflicts',
        ]);
    }

    /**
     * Test that user can duplicate a mod pack.
     */
    public function test_user_can_duplicate_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Pack',
            'description' => 'Original description',
        ]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'sort_order' => 1,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Applied Energistics 2',
            'mod_version' => '15.0.7',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/duplicate");

        $response->assertRedirect();

        // Check that new mod pack was created
        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'Original Pack (Clone)')
            ->first();

        $this->assertNotNull($newModPack);
        $this->assertEquals('Original description', $newModPack->description);
        $this->assertEquals($modPack->minecraft_version, $newModPack->minecraft_version);
        $this->assertEquals($modPack->software, $newModPack->software);

        // Check that items were copied
        $newItems = $newModPack->items()->orderBy('sort_order')->get();
        $this->assertCount(2, $newItems);
        $this->assertEquals('JEI', $newItems[0]->mod_name);
        $this->assertEquals('Applied Energistics 2', $newItems[1]->mod_name);
        $this->assertEquals(1, $newItems[0]->sort_order);
        $this->assertEquals(2, $newItems[1]->sort_order);
    }

    /**
     * Test that duplicating mod pack requires authentication.
     */
    public function test_duplicating_mod_pack_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post("/mod-packs/{$modPack->id}/duplicate");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot duplicate other user's mod pack.
     */
    public function test_user_cannot_duplicate_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/duplicate");

        $response->assertNotFound();
    }

    /**
     * Test that user can change mod pack version.
     */
    public function test_user_can_change_mod_pack_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Pack',
            'minecraft_version' => '1.19.4',
            'software' => 'forge',
        ]);

        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'source' => 'curseforge',
        ]);

        // Mock API responses for version checking and file retrieval
        Http::fake([
            'api.curseforge.com/v1/mods/238222/files*' => Http::response([
                'data' => [
                    [
                        'id' => 4700000,
                        'displayName' => 'jei-1.20.1-11.7.0.1020.jar',
                        'fileName' => 'jei-1.20.1-11.7.0.1020.jar',
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();

        // Check that new mod pack was created
        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'like', '%Updated to 1.20.1%')
            ->first();

        $this->assertNotNull($newModPack);
        $this->assertEquals('1.20.1', $newModPack->minecraft_version);
        $this->assertEquals('forge', $newModPack->software);
    }

    /**
     * Test that changing version requires authentication.
     */
    public function test_changing_version_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that changing version validates parameters.
     */
    public function test_changing_version_validates_parameters(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Test missing parameters
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", []);
        $response->assertSessionHasErrors(['minecraft_version', 'software']);

        // Test invalid software
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.20.1',
            'software' => 'invalid',
        ]);
        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that changing version returns error when mods don't have matching versions.
     */
    public function test_changing_version_returns_error_when_mods_dont_have_matching_versions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.19.4',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Incompatible Mod',
            'curseforge_mod_id' => 999999,
            'source' => 'curseforge',
        ]);

        // Mock API to return no files for the new version
        Http::fake([
            'api.curseforge.com/v1/mods/999999/files*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('version_change');
    }

    /**
     * Test that changing version redirects when same version and software.
     */
    public function test_changing_version_redirects_when_same_version_and_software(): void
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

        $response->assertRedirect("/mod-packs/{$modPack->id}");
    }

    /**
     * Test that setting reminder route doesn't exist.
     */
    public function test_setting_reminder_route_does_not_exist(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Since the reminder routes don't exist, test that the route returns 404
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/reminder", []);
        $response->assertNotFound();
    }
}
