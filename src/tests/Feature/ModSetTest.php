<?php

namespace Tests\Feature;

use App\Enums\Software;
use App\Models\ModSet;
use App\Models\ModSetItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModSetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that mod sets index page is accessible to authenticated users.
     */
    public function test_mod_sets_index_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/mod-sets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ModSets/Index'));
    }

    /**
     * Test that mod sets index redirects unauthenticated users.
     */
    public function test_mod_sets_index_redirects_unauthenticated_users(): void
    {
        $response = $this->get('/mod-sets');

        $response->assertRedirect('/login');
    }

    /**
     * Test that mod sets index shows only user's mod sets.
     */
    public function test_mod_sets_index_shows_only_user_mod_sets(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ModSet::factory()->count(3)->create(['user_id' => $user->id]);
        ModSet::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/mod-sets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModSets/Index')
            ->has('modSets', 3)
        );
    }

    /**
     * Test that mod sets index includes items relationship.
     */
    public function test_mod_sets_index_includes_items(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);
        ModSetItem::factory()->count(2)->create(['mod_set_id' => $modSet->id]);

        $response = $this->actingAs($user)->get('/mod-sets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModSets/Index')
            ->has('modSets.0.items', 2)
        );
    }

    /**
     * Test that user can create a mod set.
     */
    public function test_user_can_create_mod_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test mod pack',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_sets', [
            'user_id' => $user->id,
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test mod pack',
        ]);
    }

    /**
     * Test that creating mod set requires authentication.
     */
    public function test_creating_mod_set_requires_authentication(): void
    {
        $response = $this->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that creating mod set requires name.
     */
    public function test_creating_mod_set_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test that creating mod set requires minecraft version.
     */
    public function test_creating_mod_set_requires_minecraft_version(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('minecraft_version');
    }

    /**
     * Test that creating mod set requires software.
     */
    public function test_creating_mod_set_requires_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that creating mod set validates software enum.
     */
    public function test_creating_mod_set_validates_software_enum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'invalid',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that creating mod set accepts forge software.
     */
    public function test_creating_mod_set_accepts_forge_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_sets', [
            'software' => 'forge',
        ]);
    }

    /**
     * Test that creating mod set accepts fabric software.
     */
    public function test_creating_mod_set_accepts_fabric_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_sets', [
            'software' => 'fabric',
        ]);
    }

    /**
     * Test that description is optional when creating mod set.
     */
    public function test_creating_mod_set_description_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-sets', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_sets', [
            'name' => 'Test Mod Pack',
            'description' => null,
        ]);
    }

    /**
     * Test that user can view their mod set.
     */
    public function test_user_can_view_their_mod_set(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-sets/{$modSet->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModSets/Show')
            ->has('modSet')
            ->where('modSet.id', $modSet->id)
        );
    }

    /**
     * Test that viewing mod set requires authentication.
     */
    public function test_viewing_mod_set_requires_authentication(): void
    {
        $modSet = ModSet::factory()->create();

        $response = $this->get("/mod-sets/{$modSet->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot view other user's mod set.
     */
    public function test_user_cannot_view_other_user_mod_set(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-sets/{$modSet->id}");

        $response->assertNotFound();
    }

    /**
     * Test that viewing mod set includes items.
     */
    public function test_viewing_mod_set_includes_items(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);
        ModSetItem::factory()->count(3)->create(['mod_set_id' => $modSet->id]);

        $response = $this->actingAs($user)->get("/mod-sets/{$modSet->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modSet.items', 3)
        );
    }

    /**
     * Test that user can update their mod set.
     */
    public function test_user_can_update_their_mod_set(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
            'software' => Software::Forge,
        ]);

        $response = $this->actingAs($user)->put("/mod-sets/{$modSet->id}", [
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_sets', [
            'id' => $modSet->id,
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'fabric',
            'description' => 'Updated description',
        ]);
    }

    /**
     * Test that updating mod set requires authentication.
     */
    public function test_updating_mod_set_requires_authentication(): void
    {
        $modSet = ModSet::factory()->create();

        $response = $this->put("/mod-sets/{$modSet->id}", [
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'forge',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot update other user's mod set.
     */
    public function test_user_cannot_update_other_user_mod_set(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->put("/mod-sets/{$modSet->id}", [
            'name' => 'Updated Name',
            'minecraft_version' => '1.21.0',
            'software' => 'forge',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that updating mod set requires name.
     */
    public function test_updating_mod_set_requires_name(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/mod-sets/{$modSet->id}", [
            'minecraft_version' => '1.21.0',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test that user can delete their mod set.
     */
    public function test_user_can_delete_their_mod_set(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/mod-sets/{$modSet->id}");

        $response->assertRedirect('/mod-sets');
        $this->assertDatabaseMissing('mod_sets', [
            'id' => $modSet->id,
        ]);
    }

    /**
     * Test that deleting mod set requires authentication.
     */
    public function test_deleting_mod_set_requires_authentication(): void
    {
        $modSet = ModSet::factory()->create();

        $response = $this->delete("/mod-sets/{$modSet->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot delete other user's mod set.
     */
    public function test_user_cannot_delete_other_user_mod_set(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->delete("/mod-sets/{$modSet->id}");

        $response->assertNotFound();
    }

    /**
     * Test that deleting mod set also deletes its items.
     */
    public function test_deleting_mod_set_deletes_items(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);
        $item = ModSetItem::factory()->create(['mod_set_id' => $modSet->id]);

        $this->actingAs($user)->delete("/mod-sets/{$modSet->id}");

        $this->assertDatabaseMissing('mod_set_items', [
            'id' => $item->id,
        ]);
    }

    /**
     * Test that user can add mod item to their mod set.
     */
    public function test_user_can_add_mod_item_to_mod_set(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-sets/{$modSet->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_set_items', [
            'mod_set_id' => $modSet->id,
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);
    }

    /**
     * Test that adding mod item requires authentication.
     */
    public function test_adding_mod_item_requires_authentication(): void
    {
        $modSet = ModSet::factory()->create();

        $response = $this->post("/mod-sets/{$modSet->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot add mod item to other user's mod set.
     */
    public function test_user_cannot_add_mod_item_to_other_user_mod_set(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post("/mod-sets/{$modSet->id}/items", [
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
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-sets/{$modSet->id}/items", [
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
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post("/mod-sets/{$modSet->id}/items", [
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
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);
        ModSetItem::factory()->create([
            'mod_set_id' => $modSet->id,
            'sort_order' => 5,
        ]);

        $this->actingAs($user)->post("/mod-sets/{$modSet->id}/items", [
            'mod_name' => 'JEI',
            'mod_version' => '1.20.1-11.6.0.1015',
        ]);

        $this->assertDatabaseHas('mod_set_items', [
            'mod_set_id' => $modSet->id,
            'mod_name' => 'JEI',
            'sort_order' => 6,
        ]);
    }

    /**
     * Test that user can remove mod item from their mod set.
     */
    public function test_user_can_remove_mod_item_from_mod_set(): void
    {
        $user = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $user->id]);
        $item = ModSetItem::factory()->create(['mod_set_id' => $modSet->id]);

        $response = $this->actingAs($user)->delete("/mod-sets/{$modSet->id}/items/{$item->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('mod_set_items', [
            'id' => $item->id,
        ]);
    }

    /**
     * Test that removing mod item requires authentication.
     */
    public function test_removing_mod_item_requires_authentication(): void
    {
        $modSet = ModSet::factory()->create();
        $item = ModSetItem::factory()->create(['mod_set_id' => $modSet->id]);

        $response = $this->delete("/mod-sets/{$modSet->id}/items/{$item->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot remove mod item from other user's mod set.
     */
    public function test_user_cannot_remove_mod_item_from_other_user_mod_set(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modSet = ModSet::factory()->create(['user_id' => $otherUser->id]);
        $item = ModSetItem::factory()->create(['mod_set_id' => $modSet->id]);

        $response = $this->actingAs($user)->delete("/mod-sets/{$modSet->id}/items/{$item->id}");

        $response->assertNotFound();
    }

    /**
     * Test that mod sets are ordered by latest first.
     */
    public function test_mod_sets_are_ordered_by_latest_first(): void
    {
        $user = User::factory()->create();
        $oldModSet = ModSet::factory()->create(['user_id' => $user->id]);
        $oldModSet->created_at = now()->subDays(2);
        $oldModSet->save();

        $newModSet = ModSet::factory()->create(['user_id' => $user->id]);
        $newModSet->created_at = now();
        $newModSet->save();

        $response = $this->actingAs($user)->get('/mod-sets');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modSets', 2)
            ->where('modSets.0.id', $newModSet->id)
            ->where('modSets.1.id', $oldModSet->id)
        );
    }
}
