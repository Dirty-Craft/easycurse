<?php

namespace Tests\Feature;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that mod packs index page is accessible to authenticated users.
     */
    public function test_mod_packs_index_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ModPacks/Index'));
    }

    /**
     * Test that mod packs index redirects unauthenticated users.
     */
    public function test_mod_packs_index_redirects_unauthenticated_users(): void
    {
        $response = $this->get('/mod-packs');

        $response->assertRedirect('/login');
    }

    /**
     * Test that mod packs index shows only user's mod packs.
     */
    public function test_mod_packs_index_shows_only_user_mod_packs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ModPack::factory()->count(3)->create(['user_id' => $user->id]);
        ModPack::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Index')
            ->has('modPacks', 3)
        );
    }

    /**
     * Test that mod packs index includes items relationship.
     */
    public function test_mod_packs_index_includes_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        ModPackItem::factory()->count(2)->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Index')
            ->has('modPacks.0.items', 2)
        );
    }

    /**
     * Test that user can create a mod pack.
     */
    public function test_user_can_create_mod_pack(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test mod pack',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'user_id' => $user->id,
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test mod pack',
        ]);
    }

    /**
     * Test that creating mod pack requires authentication.
     */
    public function test_creating_mod_pack_requires_authentication(): void
    {
        $response = $this->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that creating mod pack requires name.
     */
    public function test_creating_mod_pack_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test that creating mod pack requires minecraft version.
     */
    public function test_creating_mod_pack_requires_minecraft_version(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('minecraft_version');
    }

    /**
     * Test that creating mod pack requires software.
     */
    public function test_creating_mod_pack_requires_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that creating mod pack validates software enum.
     */
    public function test_creating_mod_pack_validates_software_enum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'invalid',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that creating mod pack accepts forge software.
     */
    public function test_creating_mod_pack_accepts_forge_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'software' => 'forge',
        ]);
    }

    /**
     * Test that creating mod pack accepts fabric software.
     */
    public function test_creating_mod_pack_accepts_fabric_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'software' => 'fabric',
        ]);
    }

    /**
     * Test that description is optional when creating mod pack.
     */
    public function test_creating_mod_pack_description_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'name' => 'Test Mod Pack',
            'description' => null,
        ]);
    }

    /**
     * Test that user can view their mod pack.
     */
    public function test_user_can_view_their_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Show')
            ->has('modPack')
            ->where('modPack.id', $modPack->id)
        );
    }

    /**
     * Test that viewing mod pack requires authentication.
     */
    public function test_viewing_mod_pack_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot view other user's mod pack.
     */
    public function test_user_cannot_view_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}");

        $response->assertNotFound();
    }

    /**
     * Test that viewing mod pack includes items.
     */
    public function test_viewing_mod_pack_includes_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        ModPackItem::factory()->count(3)->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modPack.items', 3)
        );
    }

    /**
     * Test that user can update their mod pack.
     */
    public function test_user_can_update_their_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
            'minecraft_version' => '1.20.2',
            'software' => 'forge',
        ]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'id' => $modPack->id,
            'name' => 'Updated Name',
            'minecraft_version' => '1.20.2', // Should remain unchanged
            'software' => 'forge', // Should remain unchanged
            'description' => 'Updated description',
        ]);
    }

    /**
     * Test that updating mod pack requires authentication.
     */
    public function test_updating_mod_pack_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->put("/mod-packs/{$modPack->id}", [
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'forge',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot update other user's mod pack.
     */
    public function test_user_cannot_update_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}", [
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'forge',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that updating mod pack requires name.
     */
    public function test_updating_mod_pack_requires_name(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}", [
            'minecraft_version' => '1.21.0',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test that user can delete their mod pack.
     */
    public function test_user_can_delete_their_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}");

        $response->assertRedirect('/mod-packs');
        $this->assertDatabaseMissing('mod_packs', [
            'id' => $modPack->id,
        ]);
    }

    /**
     * Test that deleting mod pack requires authentication.
     */
    public function test_deleting_mod_pack_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->delete("/mod-packs/{$modPack->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot delete other user's mod pack.
     */
    public function test_user_cannot_delete_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}");

        $response->assertNotFound();
    }

    /**
     * Test that deleting mod pack also deletes its items.
     */
    public function test_deleting_mod_pack_deletes_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $this->actingAs($user)->delete("/mod-packs/{$modPack->id}");

        $this->assertDatabaseMissing('mod_pack_items', [
            'id' => $item->id,
        ]);
    }

    /**
     * Test that user can add mod item to their mod pack.
     */
    public function test_user_can_add_mod_item_to_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);
    }

    /**
     * Test that adding mod item requires authentication.
     */
    public function test_adding_mod_item_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot add mod item to other user's mod pack.
     */
    public function test_user_cannot_add_mod_item_to_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that adding mod item requires mod name.
     */
    public function test_adding_mod_item_requires_mod_name(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertSessionHasErrors('mod_name');
    }

    /**
     * Test that adding mod item requires mod version.
     */
    public function test_adding_mod_item_requires_mod_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
        ]);

        $response->assertSessionHasErrors('mod_version');
    }

    /**
     * Test that adding mod item sets correct sort order.
     */
    public function test_adding_mod_item_sets_sort_order(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'sort_order' => 5,
        ]);

        $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'sort_order' => 6,
        ]);
    }

    /**
     * Test that user can remove mod item from their mod pack.
     */
    public function test_user_can_remove_mod_item_from_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}/items/{$item->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('mod_pack_items', [
            'id' => $item->id,
        ]);
    }

    /**
     * Test that removing mod item requires authentication.
     */
    public function test_removing_mod_item_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->delete("/mod-packs/{$modPack->id}/items/{$item->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot remove mod item from other user's mod pack.
     */
    public function test_user_cannot_remove_mod_item_from_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->delete("/mod-packs/{$modPack->id}/items/{$item->id}");

        $response->assertNotFound();
    }

    /**
     * Test that mod packs are ordered by latest first.
     */
    public function test_mod_packs_are_ordered_by_latest_first(): void
    {
        $user = User::factory()->create();
        $oldModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $oldModPack->created_at = now()->subDays(2);
        $oldModPack->save();

        $newModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $newModPack->created_at = now();
        $newModPack->save();

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modPacks', 2)
            ->where('modPacks.0.id', $newModPack->id)
            ->where('modPacks.1.id', $oldModPack->id)
        );
    }

    /**
     * Test that user can search for mods.
     */
    public function test_user_can_search_for_mods(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=jei*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/search*searchFilter=jei*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=jei");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);
    }

    /**
     * Test that searching mods requires authentication.
     */
    public function test_searching_mods_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/search-mods", [
            'query' => 'jei',
        ]);

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

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods", [
            'query' => 'jei',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that searching mods requires query parameter.
     */
    public function test_searching_mods_requires_query(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods");

        $response->assertStatus(302);
        $response->assertSessionHasErrors('query');
    }

    /**
     * Test that user can get mod files.
     */
    public function test_user_can_get_mod_files(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => ['1.20.1'], // File supports exactly 1.20.1
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'displayName', 'fileName'],
            ],
        ]);

        // Verify that the file is returned (strict version matching should pass)
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(789012, $data[0]['id']);

        // Also test with quilt software to cover quilt modLoaderType case (line 106)
        $quiltModPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'quilt',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789013,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$quiltModPack->id}/mod-files?mod_id=123456");
        $response->assertStatus(200);
        $quiltData = $response->json('data');
        $this->assertCount(1, $quiltData);
    }

    /**
     * Test that get mod files handles API errors gracefully.
     */
    public function test_get_mod_files_handles_api_errors(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should return empty array on error
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    /**
     * Test that get mod files handles files with non-string gameVersions.
     */
    public function test_get_mod_files_handles_non_string_game_versions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => [
                            ['versionString' => '1.20.1'], // Array format
                            '1.20.1', // String format
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    /**
     * Test that get mod files handles files with string gameVersion field.
     */
    public function test_get_mod_files_handles_string_game_version_field(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersion' => '1.20.1', // Single string field
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    /**
     * Test that getting mod files requires authentication.
     */
    public function test_getting_mod_files_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/mod-files", [
            'mod_id' => 123456,
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot get mod files for other user's mod pack.
     */
    public function test_user_cannot_get_mod_files_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files", [
            'mod_id' => 123456,
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that getting mod files requires mod_id parameter.
     */
    public function test_getting_mod_files_requires_mod_id(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files");

        $response->assertStatus(302);
        $response->assertSessionHasErrors('mod_id');
    }

    /**
     * Test that user can add mod item with CurseForge data.
     */
    public function test_user_can_add_mod_item_with_curseforge_data(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'jei',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'jei',
        ]);
    }

    /**
     * Test that adding duplicate mod returns error.
     */
    public function test_adding_duplicate_mod_returns_error(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Add mod first time
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
        ]);

        // Try to add same mod again
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'jei',
        ]);

        $response->assertSessionHasErrors('curseforge_mod_id');
        // Verify duplicate check path is covered (lines 195-196)
    }

    /**
     * Test that CurseForge fields are optional when adding mod item.
     */
    public function test_curseforge_fields_are_optional_when_adding_mod_item(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
            'curseforge_slug' => null,
        ]);
    }

    /**
     * Test that searching mods by slug works.
     */
    public function test_searching_mods_by_slug_works(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=jei");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                [
                    'id' => 123456,
                    'name' => 'JEI',
                    'slug' => 'jei',
                ],
            ],
        ]);
    }

    /**
     * Test that searching mods falls back to general search when slug search doesn't match.
     */
    public function test_searching_mods_falls_back_to_general_search(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Mock: slug search returns null, general search returns results
        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=test-mod*' => Http::response([
                'data' => [], // Slug search returns empty
            ], 200),
            'api.curseforge.com/v1/mods/search*searchFilter=test-mod*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'Test Mod',
                        'slug' => 'test-mod',
                        'downloadCount' => 50000,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=test-mod");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);
        // Verify general search path is covered (lines 129-132)
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test that mod files are filtered to strictly match the exact Minecraft version.
     * Files for "1.20.1" should NOT be returned when requesting "1.20".
     */
    public function test_mod_files_are_filtered_by_exact_minecraft_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20', // Requesting 1.20
            'software' => 'fabric',
        ]);

        // Mock CurseForge API response that includes files for multiple versions
        // The API might return files for 1.20.1 when we request 1.20
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-02T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => ['1.20.1', '1.20.2'], // File supports 1.20.1 and 1.20.2, NOT 1.20
                    ],
                    [
                        'id' => 789013,
                        'displayName' => 'JEI 1.20-11.6.0.1014',
                        'fileName' => 'jei-1.20-11.6.0.1014.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1023000,
                        'gameVersions' => ['1.20'], // File supports exactly 1.20
                    ],
                    [
                        'id' => 789014,
                        'displayName' => 'JEI 1.19.4-11.6.0.1013',
                        'fileName' => 'jei-1.19.4-11.6.0.1013.jar',
                        'fileDate' => '2023-12-01T00:00:00Z',
                        'fileLength' => 1022000,
                        'gameVersions' => ['1.19.4'], // File supports 1.19.4, NOT 1.20
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only return the file that supports exactly 1.20
        $this->assertCount(1, $data);
        $this->assertEquals(789013, $data[0]['id']); // The file with id 789013 supports 1.20
        $this->assertStringContainsString('1.20', $data[0]['displayName']);

        // Verify that files for 1.20.1 and 1.19.4 are NOT included
        $fileIds = array_column($data, 'id');
        $this->assertNotContains(789012, $fileIds); // File for 1.20.1 should be excluded
        $this->assertNotContains(789014, $fileIds); // File for 1.19.4 should be excluded
    }

    /**
     * Test that mod files filtering handles version strings with loader suffixes.
     */
    public function test_mod_files_filtering_handles_version_with_loader_suffixes(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789015,
                        'displayName' => 'JEI 1.20-11.6.0.1014',
                        'fileName' => 'jei-1.20-11.6.0.1014.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1023000,
                        'gameVersions' => ['1.20-Fabric'], // Version with loader suffix
                    ],
                    [
                        'id' => 789016,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-02T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => ['1.20.1-Fabric'], // Different version with loader suffix
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only return the file that supports 1.20 (normalized from "1.20-Fabric")
        $this->assertCount(1, $data);
        $this->assertEquals(789015, $data[0]['id']); // The file with id 789015 supports 1.20

        // Verify that file for 1.20.1 is NOT included
        $fileIds = array_column($data, 'id');
        $this->assertNotContains(789016, $fileIds); // File for 1.20.1 should be excluded
    }

    /**
     * Test that mod files filtering returns empty array when no exact version match exists.
     */
    public function test_mod_files_filtering_returns_empty_when_no_exact_match(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20', // Requesting 1.20
            'software' => 'fabric',
        ]);

        // Mock API response with files that don't support 1.20
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789017,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-02T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => ['1.20.1'], // Only supports 1.20.1, not 1.20
                    ],
                    [
                        'id' => 789018,
                        'displayName' => 'JEI 1.19.4-11.6.0.1013',
                        'fileName' => 'jei-1.19.4-11.6.0.1013.jar',
                        'fileDate' => '2023-12-01T00:00:00Z',
                        'fileLength' => 1022000,
                        'gameVersions' => ['1.19.4'], // Only supports 1.19.4, not 1.20
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should return empty array since no files support exactly 1.20
        $this->assertCount(0, $data);
    }

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
     * Test that mod items without CurseForge IDs are copied as-is.
     */
    public function test_mod_items_without_curseforge_ids_are_copied_as_is(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create mod item without CurseForge ID
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
            'curseforge_slug' => null,
            'sort_order' => 1,
        ]);

        // No HTTP mocking needed since there's no CurseForge validation
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        $response->assertRedirect();

        // Get the new mod pack
        $newModPack = ModPack::where('name', 'My Mod Pack (Updated to 1.21.0 Fabric)')->first();

        // Verify item was copied as-is
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $newModPack->id,
            'mod_name' => 'Custom Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
            'curseforge_slug' => null,
            'sort_order' => 1,
        ]);
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

    /**
     * Test that user can get download links for mod pack items.
     */
    public function test_user_can_get_download_links(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'OptiFine',
            'curseforge_mod_id' => 234567,
            'curseforge_file_id' => 890123,
        ]);

        // Item without CurseForge data (should be skipped)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([
                'data' => [
                    'id' => 789012,
                    'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/7890/12/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/mods/234567/files/890123*' => Http::response([
                'data' => [
                    'id' => 890123,
                    'fileName' => 'optifine-1.20.1.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/8901/23/optifine-1.20.1.jar',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/download-links");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['item_id', 'mod_name', 'mod_version', 'download_url', 'filename'],
            ],
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data); // Only items with CurseForge data
        // Items may be returned in any order, so check both items are present
        $itemIds = array_column($data, 'item_id');
        $this->assertContains($item1->id, $itemIds);
        $this->assertContains($item2->id, $itemIds);

        // Also test individual item download link endpoint (covers getItemDownloadLink method)
        // Need to ensure HTTP fake covers getModFile endpoint (used by getFileDownloadInfo)
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012' => Http::response([
                'data' => [
                    'id' => 789012,
                    'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/7890/12/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
        ]);
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/items/{$item1->id}/download-link");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['item_id', 'mod_name', 'mod_version', 'download_url', 'filename'],
        ]);
        $itemData = $response->json('data');
        $this->assertEquals($item1->id, $itemData['item_id']);
        $this->assertEquals('JEI', $itemData['mod_name']);
    }

    /**
     * Test that get download links handles items with only mod_id but no file_id.
     */
    public function test_get_download_links_skips_items_without_file_id(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Item with mod_id but no file_id (should be skipped)
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => null,
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/download-links");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data);

        // Also test individual item download link endpoint with missing file_id (error case)
        $item = ModPackItem::where('mod_pack_id', $modPack->id)->first();
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/items/{$item->id}/download-link");
        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'This mod item does not have CurseForge download information.',
        ]);
    }

    /**
     * Test that get download links handles items where getFileDownloadInfo returns null.
     */
    public function test_get_download_links_handles_missing_download_info(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Item with CurseForge data but file not found
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response([], 404),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/download-links");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should return empty array since download info is not available
        $this->assertCount(0, $data);

        // Also test individual item download link endpoint when getFileDownloadInfo returns null (error case)
        $item = ModPackItem::where('mod_pack_id', $modPack->id)->first();
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/items/{$item->id}/download-link");
        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Unable to retrieve download information for this mod.',
        ]);
    }

    /**
     * Test that getting download links requires authentication.
     */
    public function test_getting_download_links_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/download-links");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot get download links for other user's mod pack.
     */
    public function test_user_cannot_get_download_links_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/download-links");

        $response->assertNotFound();

        // Also test individual item download link endpoint authorization
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/items/{$item->id}/download-link");
        $response->assertNotFound();
    }

    /**
     * Test that user can use proxy download endpoint.
     */
    public function test_user_can_use_proxy_download(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake file content', 200, [
                'Content-Type' => 'application/java-archive',
            ]),
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/java-archive');
        $this->assertEquals('fake file content', $response->getContent());
    }

    /**
     * Test that proxy download accepts edge.forgecdn.net domain.
     */
    public function test_proxy_download_accepts_edge_forgecdn_net(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'edge.forgecdn.net/*' => Http::response('fake file content', 200),
        ]);

        $url = urlencode('https://edge.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(200);
    }

    /**
     * Test that proxy download handles connection exceptions.
     */
    public function test_proxy_download_handles_connection_exceptions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(504);
        $response->assertJson([
            'error' => 'Connection timeout or network error',
        ]);
    }

    /**
     * Test that proxy download handles generic exceptions.
     */
    public function test_proxy_download_handles_generic_exceptions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => function () {
                throw new \Exception('Unexpected error');
            },
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(500);
        $response->assertJsonStructure(['error']);
    }

    /**
     * Test that proxy download uses default content type when not provided.
     */
    public function test_proxy_download_uses_default_content_type(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('fake file content', 200), // No Content-Type header
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/java-archive'); // Default
    }

    /**
     * Test that proxy download rejects invalid URLs.
     */
    public function test_proxy_download_rejects_invalid_urls(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $url = urlencode('https://malicious-site.com/file.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Invalid download URL',
        ]);
    }

    /**
     * Test that proxy download handles HTTP errors.
     */
    public function test_proxy_download_handles_http_errors(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'mediafilez.forgecdn.net/*' => Http::response('Not Found', 404),
        ]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Failed to download file from CDN',
        ]);
    }

    /**
     * Test that proxy download requires authentication.
     */
    public function test_proxy_download_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot use proxy download for other user's mod pack.
     */
    public function test_user_cannot_use_proxy_download_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $url = urlencode('https://mediafilez.forgecdn.net/files/1234/567/test.jar');
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download?url={$url}");

        $response->assertNotFound();
    }

    /**
     * Test that proxy download requires url parameter.
     */
    public function test_proxy_download_requires_url(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/proxy-download");

        $response->assertSessionHasErrors('url');
    }

    /**
     * Test that changing version handles case where getLatestModFile returns null.
     * This tests the warning log path (lines 402-406).
     */
    public function test_changing_version_handles_null_latest_mod_file(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create mod item with CurseForge ID
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);

        // Mock: First call (validation) returns files, second call (getLatestModFile) returns empty
        // This simulates the edge case where validation passes but getLatestModFile returns null
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::sequence()
                ->push([
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
                ], 200)
                ->push(['data' => []], 200), // Empty response for getLatestModFile
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/change-version", [
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
        ]);

        // Should still redirect (new pack created, but item might be skipped due to null)
        $response->assertRedirect();

        // Verify new mod pack was created
        $newModPack = ModPack::where('name', 'like', '%Updated to 1.21.0%')->first();
        $this->assertNotNull($newModPack);
    }

    /**
     * Test that mod pack user relationship works.
     */
    public function test_mod_pack_user_relationship(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $modPack->user);
        $this->assertEquals($user->id, $modPack->user->id);
    }

    /**
     * Test that mod pack item mod pack relationship works.
     */
    public function test_mod_pack_item_mod_pack_relationship(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $this->assertInstanceOf(ModPack::class, $item->modPack);
        $this->assertEquals($modPack->id, $item->modPack->id);
    }

    /**
     * Test that user mod packs relationship works.
     */
    public function test_user_mod_packs_relationship(): void
    {
        $user = User::factory()->create();
        ModPack::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->modPacks);
        $this->assertInstanceOf(ModPack::class, $user->modPacks->first());
    }
}
