<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackReorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can reorder mod items in their mod pack.
     */
    public function test_user_can_reorder_mod_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Create items with specific sort_order
        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Mod 1',
            'sort_order' => 1,
        ]);
        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Mod 2',
            'sort_order' => 2,
        ]);
        $item3 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Mod 3',
            'sort_order' => 3,
        ]);

        // Reorder: move item3 to position 1 (new order: item3, item1, item2)
        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items/reorder", [
            'item_ids' => [$item3->id, $item1->id, $item2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify sort_order was updated correctly
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $item3->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $item1->id,
            'sort_order' => 2,
        ]);
        $this->assertDatabaseHas('mod_pack_items', [
            'id' => $item2->id,
            'sort_order' => 3,
        ]);
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
     * Test that reordering validates item_ids belong to mod pack.
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
     * Test that reordering requires item_ids array.
     */
    public function test_reordering_requires_item_ids(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/mod-packs/{$modPack->id}/items/reorder", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_ids']);
    }

    /**
     * Test that reordering requires at least one item.
     */
    public function test_reordering_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/mod-packs/{$modPack->id}/items/reorder", [
                'item_ids' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_ids']);
    }

    /**
     * Test that reordering updates all items with correct sort_order.
     */
    public function test_reordering_updates_all_items_sort_order(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        // Create 5 items
        $items = ModPackItem::factory()->count(5)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        // Get item IDs in reverse order
        $itemIds = $items->reverse()->pluck('id')->toArray();

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/items/reorder", [
            'item_ids' => $itemIds,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify all items have correct sort_order (1-5 in reverse order)
        foreach ($items->reverse()->values() as $index => $item) {
            $this->assertDatabaseHas('mod_pack_items', [
                'id' => $item->id,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
