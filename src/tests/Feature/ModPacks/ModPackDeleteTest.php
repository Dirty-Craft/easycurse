<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackDeleteTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that user cannot delete bulk items from other user's mod pack.
     */
    public function test_user_cannot_delete_bulk_items_from_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/bulk-items/delete", [
            'item_ids' => [$item->id],
        ]);

        $response->assertNotFound();
    }
}
