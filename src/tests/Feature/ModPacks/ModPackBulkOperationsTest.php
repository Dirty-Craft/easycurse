<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can delete multiple mod items in bulk.
     */
    public function test_user_can_delete_bulk_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $item1 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $item2 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $item3 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-items/delete", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $response->assertRedirect("/mod-packs/{$modPack->id}");

        // Verify items were deleted
        $this->assertDatabaseMissing('mod_pack_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('mod_pack_items', ['id' => $item2->id]);
        $this->assertDatabaseHas('mod_pack_items', ['id' => $item3->id]);
    }

    /**
     * Test that bulk delete requires authentication.
     */
    public function test_bulk_delete_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->post("/mod-packs/{$modPack->id}/bulk-items/delete", [
            'item_ids' => [$item->id],
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that bulk delete validates item_ids belong to mod pack.
     */
    public function test_bulk_delete_validates_item_ownership(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $otherModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item1 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $item2 = ModPackItem::factory()->create(['mod_pack_id' => $otherModPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-items/delete", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'One or more selected items do not belong to this mod pack.',
        ]);

        // Verify no items were deleted
        $this->assertDatabaseHas('mod_pack_items', ['id' => $item1->id]);
        $this->assertDatabaseHas('mod_pack_items', ['id' => $item2->id]);
    }

    /**
     * Test that user can update bulk items to latest version.
     */
    public function test_user_can_update_bulk_items_to_latest(): void
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
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
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
            "/mod-packs/{$modPack->id}/bulk-items/update-to-latest",
            [
                'item_ids' => [$item1->id, $item2->id],
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertArrayHasKey('updated_count', $response->json());
        $this->assertArrayHasKey('failed_count', $response->json());
    }

    /**
     * Test that updating bulk items requires authentication.
     */
    public function test_updating_bulk_items_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->post(
            "/mod-packs/{$modPack->id}/bulk-items/update-to-latest",
            [
                'item_ids' => [$item->id],
            ]
        );

        $response->assertRedirect('/login');
    }

    /**
     * Test that updating bulk items validates item ownership.
     */
    public function test_updating_bulk_items_validates_item_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $otherModPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $otherModPack->id]);

        $response = $this->actingAs($user)->post(
            "/mod-packs/{$modPack->id}/bulk-items/update-to-latest",
            [
                'item_ids' => [$item->id],
            ]
        );

        $response->assertStatus(400);
        $response->assertJson([
            'error' => __('messages.modpack.invalid_item_ids'),
        ]);
    }
}
