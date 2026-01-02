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
            'software' => 'forge',
        ]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}", [
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'id' => $modPack->id,
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
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
            'api.curseforge.com/v1/*' => Http::response([
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
            'api.curseforge.com/v1/*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1024000,
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
}
