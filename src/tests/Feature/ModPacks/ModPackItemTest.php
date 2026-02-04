<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackItemTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that user can add mod item with Modrinth data.
     */
    public function test_user_can_add_mod_item_with_modrinth_data(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Sodium',
            'mod_version' => '0.5.3',
            'modrinth_project_id' => 'AANobbMI',
            'modrinth_version_id' => 'version-id-123',
            'modrinth_slug' => 'sodium',
            'source' => 'modrinth',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Sodium',
            'mod_version' => '0.5.3',
            'modrinth_project_id' => 'AANobbMI',
            'modrinth_version_id' => 'version-id-123',
            'modrinth_slug' => 'sodium',
            'source' => 'modrinth',
        ]);
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
     * Test that adding shared modpack copies all items with correct data.
     */
    public function test_adding_shared_modpack_copies_all_items(): void
    {
        $sharer = User::factory()->create();
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $sharer->id]);
        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod 1',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123,
            'curseforge_file_id' => 456,
            'curseforge_slug' => 'test-mod-1',
            'sort_order' => 1,
        ]);
        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod 2',
            'mod_version' => '2.0.0',
            'curseforge_mod_id' => 789,
            'curseforge_file_id' => 101112,
            'curseforge_slug' => 'test-mod-2',
            'sort_order' => 2,
        ]);
        $token = $modPack->generateShareToken();

        $this->actingAs($user)->post("/shared/{$token}/add-to-collection");

        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'like', '%(Shared by%')
            ->first();
        $this->assertNotNull($newModPack);

        $newItems = $newModPack->items()->orderBy('sort_order')->get();
        $this->assertCount(2, $newItems);
        $this->assertEquals('Test Mod 1', $newItems[0]->mod_name);
        $this->assertEquals('1.0.0', $newItems[0]->mod_version);
        $this->assertEquals(123, $newItems[0]->curseforge_mod_id);
        $this->assertEquals(456, $newItems[0]->curseforge_file_id);
        $this->assertEquals('test-mod-1', $newItems[0]->curseforge_slug);
        $this->assertEquals(1, $newItems[0]->sort_order);
    }

    /**
     * Test that user can update a mod item.
     */
    public function test_user_can_update_mod_item(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);
        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
            'curseforge_slug' => 'jei',
        ]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'JEI Updated',
            'mod_version' => '1.20.1-11.6.0.1016',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123457,
            'curseforge_slug' => 'jei',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $item->id,
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI Updated',
            'mod_version' => '1.20.1-11.6.0.1016',
            'curseforge_file_id' => 123457,
        ]);
    }

    /**
     * Test that user cannot reorder items from other user's mod pack.
     */
    public function test_user_cannot_reorder_items_from_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items/reorder", [
            'item_ids' => [$item->id],
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that user can reorder items in their mod pack.
     */
    public function test_user_can_reorder_items_in_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'sort_order' => 1,
        ]);
        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'sort_order' => 2,
        ]);
        $item3 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'sort_order' => 3,
        ]);

        // Reorder: item3, item1, item2
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items/reorder", [
            'item_ids' => [$item3->id, $item1->id, $item2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Check new sort orders
        $item1->refresh();
        $item2->refresh();
        $item3->refresh();

        $this->assertEquals(2, $item1->sort_order);
        $this->assertEquals(3, $item2->sort_order);
        $this->assertEquals(1, $item3->sort_order);
    }

    /**
     * Test that reordering items requires authentication.
     */
    public function test_reordering_items_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->post("/mod-packs/{$modPack->id}/items/reorder", [
            'item_ids' => [$item->id],
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that reordering validates item ownership.
     */
    public function test_reordering_validates_item_ownership(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $otherModPack = ModPack::factory()->create(['user_id' => $user->id]);

        $item1 = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);
        $item2 = ModPackItem::factory()->create(['mod_pack_id' => $otherModPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items/reorder", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'One or more selected items do not belong to this mod pack.',
        ]);
    }

    /**
     * Test that user can add mod item with both CurseForge and Modrinth data.
     */
    public function test_user_can_add_mod_item_with_mixed_platform_data(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'test-mod',
            'modrinth_project_id' => 'AANobbMI',
            'modrinth_version_id' => 'version-id-123',
            'modrinth_slug' => 'test-mod',
            'source' => 'curseforge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'curseforge_mod_id' => 123456,
            'modrinth_project_id' => 'AANobbMI',
            'source' => 'curseforge',
        ]);
    }

    /**
     * Test that adding duplicate CurseForge mod returns error.
     */
    public function test_adding_duplicate_curseforge_mod_returns_error(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Add first mod
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 123456,
        ]);

        // Try to add same mod again
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Duplicate Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);

        $response->assertSessionHasErrors('curseforge_mod_id');
    }

    /**
     * Test that adding duplicate Modrinth mod returns error.
     */
    public function test_adding_duplicate_modrinth_mod_returns_error(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Add first mod
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'modrinth_project_id' => 'AANobbMI',
        ]);

        // Try to add same mod again
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Duplicate Mod',
            'mod_version' => '1.0.0',
            'modrinth_project_id' => 'AANobbMI',
            'modrinth_version_id' => 'version-id-123',
            'source' => 'modrinth',
        ]);

        $response->assertSessionHasErrors('modrinth_project_id');
    }

    /**
     * Test that source is automatically determined from mod IDs.
     */
    public function test_source_is_automatically_determined_from_mod_ids(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Test CurseForge auto-detection
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'CurseForge Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_name' => 'CurseForge Mod',
            'source' => 'curseforge',
        ]);

        // Test Modrinth auto-detection
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items", [
            'mod_name' => 'Modrinth Mod',
            'mod_version' => '1.0.0',
            'modrinth_project_id' => 'AANobbMI',
            'modrinth_version_id' => 'version-id-123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_name' => 'Modrinth Mod',
            'source' => 'modrinth',
        ]);
    }

    /**
     * Test that updating mod item requires authentication.
     */
    public function test_updating_mod_item_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'Updated Mod',
            'mod_version' => '2.0.0',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot update mod item in other user's mod pack.
     */
    public function test_user_cannot_update_mod_item_in_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'Updated Mod',
            'mod_version' => '2.0.0',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that updating mod item validates required fields.
     */
    public function test_updating_mod_item_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            // Missing mod_name and mod_version
        ]);

        $response->assertSessionHasErrors(['mod_name', 'mod_version']);
    }
}
