<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackDownloadTest extends TestCase
{
    use RefreshDatabase;

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

        // Verify initial downloads_count is 0
        $modPack->refresh();
        $this->assertEquals(0, $modPack->downloads_count);

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

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);

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
        // Reset downloads_count for individual download test
        $modPack->update(['downloads_count' => 0]);
        $modPack->refresh();
        $this->assertEquals(0, $modPack->downloads_count);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/items/{$item1->id}/download-link");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['item_id', 'mod_name', 'mod_version', 'download_url', 'filename'],
        ]);
        $itemData = $response->json('data');
        $this->assertEquals($item1->id, $itemData['item_id']);
        $this->assertEquals('JEI', $itemData['mod_name']);

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
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
     * Test that user can get bulk download links for selected items.
     */
    public function test_user_can_get_bulk_download_links(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Create items with CurseForge data
        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Applied Energistics 2',
            'curseforge_mod_id' => 223794,
            'curseforge_file_id' => 4639210,
        ]);

        // Item without CurseForge data (should be skipped)
        $item3 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256*' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-forge-12.3.0.15.jar',
                ],
            ]),
            'api.curseforge.com/v1/mods/223794/files/4639210*' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4639/210/appliedenergistics2-forge-15.0.7.jar',
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-download-links", [
            'item_ids' => [$item1->id, $item2->id, $item3->id],
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data); // Only items with CurseForge data

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that bulk download links requires authentication.
     */
    public function test_bulk_download_links_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->post("/mod-packs/{$modPack->id}/bulk-download-links", [
            'item_ids' => [$item->id],
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot get bulk download links for other user's mod pack.
     */
    public function test_user_cannot_get_bulk_download_links_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-download-links", [
            'item_ids' => [$item->id],
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that bulk download links validates item_ids belong to mod pack.
     */
    public function test_bulk_download_links_validates_item_ownership(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $otherModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item1 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $item2 = ModPackItem::factory()->create(['mod_pack_id' => $otherModPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-download-links", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'One or more selected items do not belong to this mod pack.',
        ]);
    }

    /**
     * Test that user can get download links for shared modpack.
     */
    public function test_user_can_get_download_links_for_shared_modpack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);
        $token = $modPack->generateShareToken();

        // Verify initial downloads_count is 0
        $modPack->refresh();
        $this->assertEquals(0, $modPack->downloads_count);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012' => Http::response([
                'data' => [
                    'id' => 789012,
                    'fileName' => 'test.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/789012/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/789/12/test.jar',
                ],
            ], 200),
        ]);

        $response = $this->get("/shared/{$token}/download-links");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'item_id',
                    'mod_name',
                    'mod_version',
                    'download_url',
                    'filename',
                ],
            ],
        ]);

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that user can get download link for specific item in shared modpack.
     */
    public function test_user_can_get_download_link_for_specific_item_in_shared_modpack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);
        $token = $modPack->generateShareToken();

        // Verify initial downloads_count is 0
        $modPack->refresh();
        $this->assertEquals(0, $modPack->downloads_count);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012' => Http::response([
                'data' => [
                    'id' => 789012,
                    'fileName' => 'test.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/789012/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/789/12/test.jar',
                ],
            ], 200),
        ]);

        $response = $this->get("/shared/{$token}/items/{$item->id}/download-link");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'item_id',
                'mod_name',
                'mod_version',
                'download_url',
                'filename',
            ],
        ]);
        $response->assertJson([
            'data' => [
                'item_id' => $item->id,
                'mod_name' => 'Test Mod',
                'mod_version' => '1.0.0',
            ],
        ]);

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that invalid share token for download links returns 404.
     */
    public function test_invalid_share_token_for_download_links_returns_404(): void
    {
        $response = $this->get('/shared/invalid-token-12345/download-links');

        $response->assertNotFound();
    }

    /**
     * Test that invalid item ID for shared modpack download link returns 404.
     */
    public function test_invalid_item_id_for_shared_modpack_download_link_returns_404(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}/items/99999/download-link");

        $response->assertNotFound();
    }

    /**
     * Test that getSharedDownloadLinks skips items without CurseForge metadata.
     */
    public function test_get_shared_download_links_skips_items_without_curseforge_metadata(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        // Create item without CurseForge metadata
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);
        // Create item with CurseForge metadata
        $itemWithMetadata = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);
        $token = $modPack->generateShareToken();

        // Verify initial downloads_count is 0
        $modPack->refresh();
        $this->assertEquals(0, $modPack->downloads_count);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012' => Http::response([
                'data' => [
                    'id' => 789012,
                    'fileName' => 'test.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/789012/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/789/12/test.jar',
                ],
            ], 200),
        ]);

        $response = $this->get("/shared/{$token}/download-links");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($itemWithMetadata->id, $data[0]['item_id']);

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that getSharedItemDownloadLink returns error when item lacks CurseForge metadata.
     */
    public function test_get_shared_item_download_link_returns_error_when_item_lacks_curseforge_metadata(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}/items/{$item->id}/download-link");

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'This mod item does not have CurseForge download information.',
        ]);
    }

    /**
     * Test that user can get bulk download links for selected items in shared modpack.
     */
    public function test_user_can_get_bulk_download_links_for_shared_modpack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Create items with CurseForge data
        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Applied Energistics 2',
            'curseforge_mod_id' => 223794,
            'curseforge_file_id' => 4639210,
        ]);

        // Item without CurseForge data (should be skipped)
        $item3 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);

        $token = $modPack->generateShareToken();

        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256*' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-forge-12.3.0.15.jar',
                ],
            ]),
            'api.curseforge.com/v1/mods/223794/files/4639210*' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4639/210/appliedenergistics2-forge-15.0.7.jar',
                ],
            ]),
        ]);

        $response = $this->post("/shared/{$token}/bulk-download-links", [
            'item_ids' => [$item1->id, $item2->id, $item3->id],
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data); // Only items with CurseForge data

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that bulk download links for shared modpack validates item ownership.
     */
    public function test_bulk_download_links_for_shared_modpack_validates_item_ownership(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $otherModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item1 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $item2 = ModPackItem::factory()->create(['mod_pack_id' => $otherModPack->id]);
        $token = $modPack->generateShareToken();

        $response = $this->post("/shared/{$token}/bulk-download-links", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'One or more selected items do not belong to this mod pack.',
        ]);
    }

    /**
     * Test that bulk download links for shared modpack works without authentication.
     */
    public function test_bulk_download_links_for_shared_modpack_works_without_authentication(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
        ]);

        $token = $modPack->generateShareToken();

        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256*' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-forge-12.3.0.15.jar',
                ],
            ]),
        ]);

        // Make request without authentication
        $response = $this->post("/shared/{$token}/bulk-download-links", [
            'item_ids' => [$item1->id],
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    /**
     * Test that invalid share token for bulk download links returns 404.
     */
    public function test_invalid_share_token_for_bulk_download_links_returns_404(): void
    {
        $response = $this->post('/shared/invalid-token-12345/bulk-download-links', [
            'item_ids' => [1, 2, 3],
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that getSharedItemDownloadLink returns error when downloadInfo is null.
     */
    public function test_get_shared_item_download_link_returns_error_when_download_info_is_null(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);
        $token = $modPack->generateShareToken();

        // Mock CurseForgeService to return 404 for getModFile (which causes downloadInfo to be null)
        // The getModFile method uses throw() which will throw RequestException for non-2xx responses
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012' => Http::response(['error' => 'Not found'], 404),
        ]);

        $response = $this->get("/shared/{$token}/items/{$item->id}/download-link");

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Unable to retrieve download information for this mod.',
        ]);
    }
}
