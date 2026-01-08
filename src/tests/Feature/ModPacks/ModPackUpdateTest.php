<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackUpdateTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that user cannot update mod item in other user's mod pack.
     */
    public function test_user_cannot_update_mod_item_in_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->put("/mod-packs/{$modPack->id}/items/{$item->id}", [
            'mod_name' => 'JEI Updated',
            'mod_version' => '1.20.1-11.6.0.1016',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that user cannot update all items in other user's mod pack.
     */
    public function test_user_cannot_update_all_items_in_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post(
            "/mod-packs/{$modPack->id}/items/update-all-to-latest"
        );

        $response->assertNotFound();
    }

    /**
     * Test that user cannot update bulk items in other user's mod pack.
     */
    public function test_user_cannot_update_bulk_items_in_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);
        $item = ModPackItem::factory()->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->post(
            "/mod-packs/{$modPack->id}/bulk-items/update-to-latest",
            [
                'item_ids' => [$item->id],
            ]
        );

        $response->assertNotFound();
    }
}
