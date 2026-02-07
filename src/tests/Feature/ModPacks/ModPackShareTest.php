<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Tests\TestCase;

class ModPackShareTest extends TestCase
{
    /**
     * Test that user can generate share token for their mod pack.
     */
    public function test_user_can_generate_share_token(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/share");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'share_token',
            'share_url',
        ]);

        $shareToken = $response->json('share_token');
        $this->assertNotEmpty($shareToken);
        $this->assertStringContainsString($shareToken, $response->json('share_url'));

        // Verify token was saved to database
        $modPack->refresh();
        $this->assertEquals($shareToken, $modPack->share_token);
    }

    /**
     * Test that generating share token requires authentication.
     */
    public function test_generating_share_token_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->post("/mod-packs/{$modPack->id}/share");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot generate share token for other user's mod pack.
     */
    public function test_user_cannot_generate_share_token_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/share");

        $response->assertNotFound();
    }

    /**
     * Test that user can regenerate share token.
     */
    public function test_user_can_regenerate_share_token(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $originalToken = $modPack->generateShareToken();

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/share", [
            'regenerate' => true,
        ]);

        $response->assertStatus(200);
        $newToken = $response->json('share_token');
        $this->assertNotEquals($originalToken, $newToken);

        // Verify new token was saved to database
        $modPack->refresh();
        $this->assertEquals($newToken, $modPack->share_token);
    }

    /**
     * Test that existing share token is returned when not regenerating.
     */
    public function test_existing_share_token_is_returned_when_not_regenerating(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $existingToken = $modPack->generateShareToken();

        $response = $this->actingAs($user)->post("/mod-packs/{$modPack->id}/share");

        $response->assertStatus(200);
        $this->assertEquals($existingToken, $response->json('share_token'));
    }

    /**
     * Test that shared mod pack can be viewed without authentication.
     */
    public function test_shared_mod_pack_can_be_viewed_without_authentication(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Shared Pack',
            'description' => 'A shared mod pack',
        ]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Shared')
            ->has('modPack')
            ->where('modPack.id', $modPack->id)
            ->where('modPack.name', 'Shared Pack')
            ->where('sharerName', 'John Doe')
            ->where('isOwner', false)
        );
    }

    /**
     * Test that shared mod pack shows owner as true when viewed by owner.
     */
    public function test_shared_mod_pack_shows_owner_as_true_when_viewed_by_owner(): void
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
     * Test that invalid share token returns 404.
     */
    public function test_invalid_share_token_returns_404(): void
    {
        $response = $this->get('/shared/invalid-token-12345');

        $response->assertNotFound();
    }

    /**
     * Test that shared mod pack includes items.
     */
    public function test_shared_mod_pack_includes_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        ModPackItem::factory()->count(3)->create([
            'mod_pack_id' => $modPack->id,
        ]);

        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modPack.items', 3)
        );
    }

    /**
     * Test that user can add shared mod pack to their collection.
     */
    public function test_user_can_add_shared_mod_pack_to_collection(): void
    {
        $sharer = User::factory()->create(['name' => 'Jane Doe']);
        $user = User::factory()->create();

        $modPack = ModPack::factory()->create([
            'user_id' => $sharer->id,
            'name' => 'Awesome Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'An awesome mod pack',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'sort_order' => 1,
        ]);

        $token = $modPack->generateShareToken();

        $response = $this->actingAs($user)->post("/shared/{$token}/add-to-collection");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that new mod pack was created for the user
        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'Awesome Pack (Shared by Jane Doe)')
            ->first();

        $this->assertNotNull($newModPack);
        $this->assertEquals('1.20.1', $newModPack->minecraft_version);
        $this->assertEquals('forge', $newModPack->software);
        $this->assertEquals('An awesome mod pack', $newModPack->description);

        // Check that items were copied
        $newItems = $newModPack->items;
        $this->assertCount(1, $newItems);
        $this->assertEquals('JEI', $newItems[0]->mod_name);
        $this->assertEquals('11.6.0.1015', $newItems[0]->mod_version);
        $this->assertEquals(238222, $newItems[0]->curseforge_mod_id);
        $this->assertEquals(4638256, $newItems[0]->curseforge_file_id);
        $this->assertEquals(1, $newItems[0]->sort_order);
    }

    /**
     * Test that adding shared mod pack to collection requires authentication.
     */
    public function test_adding_shared_mod_pack_to_collection_requires_authentication(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->post("/shared/{$token}/add-to-collection");

        $response->assertRedirect('/login');
    }

    /**
     * Test that adding shared mod pack with invalid token returns 404.
     */
    public function test_adding_shared_mod_pack_with_invalid_token_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/shared/invalid-token-12345/add-to-collection');

        $response->assertNotFound();
    }

    /**
     * Test that shared mod pack handles user with no name.
     */
    public function test_shared_mod_pack_handles_user_with_no_name(): void
    {
        $user = User::factory()->create(['name' => '']);
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('sharerName', '')
        );
    }

    /**
     * Test that adding shared mod pack handles sharer with no name.
     */
    public function test_adding_shared_mod_pack_handles_sharer_with_no_name(): void
    {
        $sharer = User::factory()->create(['name' => '']);
        $user = User::factory()->create();

        $modPack = ModPack::factory()->create([
            'user_id' => $sharer->id,
            'name' => 'Test Pack',
        ]);

        $token = $modPack->generateShareToken();

        $response = $this->actingAs($user)->post("/shared/{$token}/add-to-collection");

        $response->assertRedirect();

        // Check that new mod pack was created with empty string as sharer name
        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'Test Pack (Shared by )')
            ->first();

        $this->assertNotNull($newModPack);
    }

    /**
     * Test that shared mod pack includes game versions and mod loaders.
     */
    public function test_shared_mod_pack_includes_game_versions_and_mod_loaders(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        $token = $modPack->generateShareToken();

        $response = $this->get("/shared/{$token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('gameVersions')
            ->has('modLoaders')
        );
    }

    /**
     * Test that shared mod pack preserves all item data when adding to collection.
     */
    public function test_shared_mod_pack_preserves_all_item_data_when_adding_to_collection(): void
    {
        $sharer = User::factory()->create(['name' => 'Sharer']);
        $user = User::factory()->create();

        $modPack = ModPack::factory()->create([
            'user_id' => $sharer->id,
            'name' => 'Complex Pack',
        ]);

        // Create item with both CurseForge and Modrinth data
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Multi-Platform Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'curseforge_slug' => 'multi-mod',
            'modrinth_project_id' => 'AANobbMI',
            'modrinth_version_id' => 'version123',
            'modrinth_slug' => 'multi-mod',
            'source' => 'curseforge',
            'sort_order' => 1,
        ]);

        $token = $modPack->generateShareToken();

        $this->actingAs($user)->post("/shared/{$token}/add-to-collection");

        $newModPack = ModPack::where('user_id', $user->id)->first();
        $newItem = $newModPack->items->first();

        $this->assertEquals('Multi-Platform Mod', $newItem->mod_name);
        $this->assertEquals('1.0.0', $newItem->mod_version);
        $this->assertEquals(123456, $newItem->curseforge_mod_id);
        $this->assertEquals(789012, $newItem->curseforge_file_id);
        $this->assertEquals('multi-mod', $newItem->curseforge_slug);
        $this->assertEquals('AANobbMI', $newItem->modrinth_project_id);
        $this->assertEquals('version123', $newItem->modrinth_version_id);
        $this->assertEquals('multi-mod', $newItem->modrinth_slug);
        $this->assertEquals('curseforge', $newItem->source);
        $this->assertEquals(1, $newItem->sort_order);
    }

    /**
     * Test that user can add empty shared mod pack to collection.
     */
    public function test_user_can_add_empty_shared_mod_pack_to_collection(): void
    {
        $sharer = User::factory()->create(['name' => 'Sharer']);
        $user = User::factory()->create();

        $modPack = ModPack::factory()->create([
            'user_id' => $sharer->id,
            'name' => 'Empty Pack',
        ]);

        $token = $modPack->generateShareToken();

        $response = $this->actingAs($user)->post("/shared/{$token}/add-to-collection");

        $response->assertRedirect();

        // Check that new mod pack was created even without items
        $newModPack = ModPack::where('user_id', $user->id)
            ->where('name', 'Empty Pack (Shared by Sharer)')
            ->first();

        $this->assertNotNull($newModPack);
        $this->assertCount(0, $newModPack->items);
    }
}
