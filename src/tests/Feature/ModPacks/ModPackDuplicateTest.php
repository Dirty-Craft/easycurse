<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackDuplicateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can duplicate their mod pack.
     */
    public function test_user_can_duplicate_their_mod_pack(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'Test description',
        ]);
        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => 123,
            'curseforge_file_id' => 456,
            'curseforge_slug' => 'jei',
            'sort_order' => 1,
        ]);
        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Another Mod',
            'mod_version' => '1.0.0',
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/duplicate");

        $response->assertRedirect();

        // Check that a new mod pack was created with " (Clone)" suffix
        $this->assertDatabaseHas('mod_packs', [
            'user_id' => $user->id,
            'name' => 'Test Mod Pack (Clone)',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'Test description',
        ]);

        // Get the new mod pack
        $newModPack = ModPack::where('name', 'Test Mod Pack (Clone)')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($newModPack);
        $this->assertNotEquals($modPack->id, $newModPack->id);

        // Check that all items were copied
        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $newModPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
            'curseforge_mod_id' => 123,
            'curseforge_file_id' => 456,
            'curseforge_slug' => 'jei',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('mod_pack_items', [
            'mod_pack_id' => $newModPack->id,
            'mod_name' => 'Another Mod',
            'mod_version' => '1.0.0',
            'sort_order' => 2,
        ]);

        // Verify original mod pack still exists
        $this->assertDatabaseHas('mod_packs', [
            'id' => $modPack->id,
            'name' => 'Test Mod Pack',
        ]);
    }

    /**
     * Test that user cannot duplicate other user's mod pack.
     */
    public function test_user_cannot_duplicate_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/duplicate");

        $response->assertNotFound();
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
}
