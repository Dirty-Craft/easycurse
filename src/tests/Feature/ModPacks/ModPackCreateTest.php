<?php

namespace Tests\Feature\ModPacks;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModPackCreateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can create a mod pack.
     */
    public function test_user_can_create_mod_pack(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test mod pack',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'user_id' => $user->id,
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test mod pack',
        ]);
    }

    /**
     * Test that creating mod pack requires authentication.
     */
    public function test_creating_mod_pack_requires_authentication(): void
    {
        $response = $this->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that creating mod pack requires name.
     */
    public function test_creating_mod_pack_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test that creating mod pack requires minecraft version.
     */
    public function test_creating_mod_pack_requires_minecraft_version(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'software' => 'forge',
        ]);

        $response->assertSessionHasErrors('minecraft_version');
    }

    /**
     * Test that creating mod pack requires software.
     */
    public function test_creating_mod_pack_requires_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that creating mod pack validates software enum.
     */
    public function test_creating_mod_pack_validates_software_enum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'invalid',
        ]);

        $response->assertSessionHasErrors('software');
    }

    /**
     * Test that creating mod pack accepts forge software.
     */
    public function test_creating_mod_pack_accepts_forge_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'software' => 'forge',
        ]);
    }

    /**
     * Test that creating mod pack accepts fabric software.
     */
    public function test_creating_mod_pack_accepts_fabric_software(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'software' => 'fabric',
        ]);
    }

    /**
     * Test that description is optional when creating mod pack.
     */
    public function test_creating_mod_pack_description_is_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mod-packs', [
            'name' => 'Test Mod Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mod_packs', [
            'name' => 'Test Mod Pack',
            'description' => null,
        ]);
    }
}
