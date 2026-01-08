<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackServiceTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that duplicating mod pack copies all item fields correctly.
     */
    public function test_duplicating_mod_pack_copies_all_item_fields(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 999,
            'curseforge_file_id' => 888,
            'curseforge_slug' => 'test-mod',
            'sort_order' => 5,
        ]);

        $this->actingAs($user)->post("/mod-packs/{$modPack->id}/duplicate");

        $newModPack = ModPack::where('name', $modPack->name.' (Clone)')
            ->where('user_id', $user->id)
            ->first();

        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $newModPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 999,
            'curseforge_file_id' => 888,
            'curseforge_slug' => 'test-mod',
            'sort_order' => 5,
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
     * Test that getMod handles API errors gracefully and success cases.
     * Covers CurseForgeService lines 73-84, including line 77 (success return).
     */
    public function test_get_mod_handles_api_errors(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Test error in getMod (covers lines 73-84)
        Http::fake([
            'api.curseforge.com/v1/mods/999999*' => Http::response(['error' => 'Not found'], 404),
        ]);

        // This would be called indirectly through the controller
        // We'll test it by making a request that would use getMod
        // Since getMod is used internally, we test it through an endpoint that uses it
        // For now, we'll just ensure the error path exists
        $service = app(\App\Services\CurseForgeService::class);
        $result = $service->getMod(999999);
        $this->assertNull($result);

        // Test success case for getMod (covers line 77)
        Http::fake([
            'api.curseforge.com/v1/mods/123456*' => Http::response([
                'data' => [
                    'id' => 123456,
                    'name' => 'Test Mod',
                    'slug' => 'test-mod',
                ],
            ], 200),
        ]);

        $result = $service->getMod(123456);
        $this->assertIsArray($result);
        $this->assertEquals(123456, $result['id']);
        $this->assertEquals('Test Mod', $result['name']);
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
     * Test that non-owner sees isOwner flag as false when viewing shared modpack.
     */
    public function test_non_owner_sees_is_owner_flag_as_false(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->actingAs($otherUser)->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('isOwner', false)
        );
    }

    /**
     * Test that updating mod item requires authentication.
     */
    public function test_updating_mod_item_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'JEI Updated',
            'mod_version' => '1.20.1-11.6.0.1016',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that updating mod item requires mod name.
     */
    public function test_updating_mod_item_requires_mod_name(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_version' => '1.20.1-11.6.0.1016',
        ]);

        $response->assertSessionHasErrors('mod_name');
    }

    /**
     * Test that updating mod item requires mod version.
     */
    public function test_updating_mod_item_requires_mod_version(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'JEI Updated',
        ]);

        $response->assertSessionHasErrors('mod_version');
    }

    /**
     * Test that updating all items requires authentication.
     */
    public function test_updating_all_items_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post(
            "/mod-packs/{$modPack->id}/items/update-all-to-latest"
        );

        $response->assertRedirect('/login');
    }

    /**
     * Test that updating all items skips items without curseforge_mod_id.
     */
    public function test_updating_all_items_skips_items_without_curseforge_mod_id(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);

        // Mock CurseForge API responses
        Http::fake([
            'api.curseforge.com/v1/mods/*/files*' => Http::response([
                'data' => [
                    [
                        'id' => 999999,
                        'displayName' => '1.20.1-11.6.0.1017',
                        'fileName' => 'jei-1.20.1-11.6.0.1017.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1000000,
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(
            "/mod-packs/{$modPack->id}/items/update-all-to-latest"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        // Should update 1 item (the one with curseforge_mod_id)
        $this->assertEquals(1, $response->json('updated_count'));
    }
}
