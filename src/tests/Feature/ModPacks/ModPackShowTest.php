<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackShowTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that mod pack show page includes searchable mod fields.
     * This test verifies that mod items have the necessary fields
     * (mod_name, mod_version, curseforge_slug) for client-side search functionality.
     */
    public function test_mod_pack_show_includes_searchable_mod_fields(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Create mod items with different searchable fields and explicit sort_order
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod One',
            'mod_version' => '1.0.0',
            'curseforge_slug' => 'test-mod-one',
            'sort_order' => 1,
        ]);
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Another Mod',
            'mod_version' => '2.1.0',
            'curseforge_slug' => 'another-mod',
            'sort_order' => 2,
        ]);
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Third Mod',
            'mod_version' => '3.5.2',
            'curseforge_slug' => null, // Some mods may not have slug
            'sort_order' => 3,
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Show')
            ->has('modPack.items', 3)
            ->where('modPack.items.0.mod_name', 'Test Mod One')
            ->where('modPack.items.0.mod_version', '1.0.0')
            ->where('modPack.items.0.curseforge_slug', 'test-mod-one')
            ->where('modPack.items.1.mod_name', 'Another Mod')
            ->where('modPack.items.1.mod_version', '2.1.0')
            ->where('modPack.items.2.mod_name', 'Third Mod')
            ->where('modPack.items.2.mod_version', '3.5.2')
        );
    }

    /**
     * Test that mod pack show page loads correctly with multiple mods for search functionality.
     * This ensures the page structure supports client-side filtering.
     * Note: The actual search filtering is tested client-side in the Vue component.
     */
    public function test_mod_pack_show_loads_with_multiple_mods_for_search(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Create multiple mods with varied names to test search, with explicit sort_order
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Jei',
            'mod_version' => '1.0.0',
            'curseforge_slug' => 'jei',
            'sort_order' => 1,
        ]);
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'OptiFine',
            'mod_version' => '2.0.0',
            'curseforge_slug' => 'optifine',
            'sort_order' => 2,
        ]);
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JourneyMap',
            'mod_version' => '3.0.0',
            'curseforge_slug' => 'journeymap',
            'sort_order' => 3,
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Show')
            ->has('modPack.items', 3)
            // Verify searchable fields are present in the response
            ->where('modPack.items.0.mod_name', 'Jei')
            ->where('modPack.items.0.mod_version', '1.0.0')
            ->where('modPack.items.1.mod_name', 'OptiFine')
            ->where('modPack.items.1.mod_version', '2.0.0')
            ->where('modPack.items.2.mod_name', 'JourneyMap')
            ->where('modPack.items.2.mod_version', '3.0.0')
        );
    }

    /**
     * Test that shared modpack can be viewed without authentication.
     */
    public function test_shared_modpack_can_be_viewed_without_authentication(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Shared')
            ->has('modPack')
            ->where('modPack.id', $modPack->id)
            ->where('sharerName', 'Test User')
        );
    }

    /**
     * Test that viewing shared modpack includes items.
     */
    public function test_viewing_shared_modpack_includes_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        ModPackItem::factory()->count(3)->create(['mod_pack_id' => $modPack->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modPack.items', 3)
        );
    }

    /**
     * Test that viewing shared modpack shows sharer name.
     */
    public function test_viewing_shared_modpack_shows_sharer_name(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('sharerName', 'John Doe')
        );
    }

    /**
     * Test that owner sees isOwner flag as true when viewing shared modpack.
     */
    public function test_owner_sees_is_owner_flag_when_viewing_shared_modpack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->actingAs($user)->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('isOwner', true)
        );
    }

    /**
     * Test that user can preview updates for all mod items.
     */
    public function test_user_can_preview_all_items_to_latest(): void
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
            'mod_version' => '1.20.1-11.5.0.1000',
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
            'mod_version' => '1.20.1-11.5.0.1000',
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

        $response = $this->actingAs($user)->get(
            "/mod-packs/{$modPack->id}/items/preview-all-to-latest"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'updates',
            'total_count',
        ]);
        $this->assertIsArray($response->json('updates'));
        $this->assertGreaterThan(0, $response->json('total_count'));
    }

    /**
     * Test that previewing all items requires authentication.
     */
    public function test_previewing_all_items_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get(
            "/mod-packs/{$modPack->id}/items/preview-all-to-latest"
        );

        $response->assertRedirect('/login');
    }

    /**
     * Test that user can preview updates for bulk items.
     */
    public function test_user_can_preview_bulk_items_to_latest(): void
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
            'mod_version' => '1.20.1-11.5.0.1000',
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
            'mod_version' => '1.20.1-11.5.0.1000',
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
            "/mod-packs/{$modPack->id}/items/preview-bulk-to-latest",
            [
                'item_ids' => [$item1->id, $item2->id],
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'updates',
            'total_count',
        ]);
        $this->assertIsArray($response->json('updates'));
    }

    /**
     * Test that previewing bulk items requires authentication.
     */
    public function test_previewing_bulk_items_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->post(
            "/mod-packs/{$modPack->id}/items/preview-bulk-to-latest",
            [
                'item_ids' => [$item->id],
            ]
        );

        $response->assertRedirect('/login');
    }
}
