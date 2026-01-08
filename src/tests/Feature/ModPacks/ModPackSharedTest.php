<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackSharedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that authenticated user can add shared modpack to collection.
     */
    public function test_authenticated_user_can_add_shared_modpack_to_collection(): void
    {
        $sharer = User::factory()->create(['name' => 'John Sharer']);
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $sharer->id,
            'name' => 'Shared Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A shared mod pack',
        ]);
        ModPackItem::factory()->count(2)->create(['mod_pack_id' => $modPack->id]);
        $token = $modPack->generateShareToken();

        $response = $this->actingAs($user)->post("/shared/{$token}/add-to-collection");

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'user_id' => $user->id,
            'name' => 'Shared Mod Pack (Shared by John Sharer)',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A shared mod pack',
        ]);

        // Verify items were copied
        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'Shared Mod Pack (Shared by John Sharer)')
            ->first();
        $this->assertNotNull($newModPack);
        $this->assertEquals(2, $newModPack->items()->count());
    }

    /**
     * Test that adding shared modpack to collection requires authentication.
     */
    public function test_adding_shared_modpack_to_collection_requires_authentication(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->post("/shared/{$token}/add-to-collection");

        $response->assertRedirect('/login');
    }
}
