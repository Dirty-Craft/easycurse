<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can search for mods.
     */
    public function test_user_can_search_for_mods(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=jei*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
            'api.curseforge.com/v1/mods/search*searchFilter=jei*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=jei");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);
    }

    /**
     * Test that searching mods handles API errors gracefully.
     * Covers error paths in searchModBySlug, searchMods, and getMod.
     */
    public function test_searching_mods_handles_api_errors(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Test error in searchModBySlug (covers lines 57-64)
        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=test-error*' => Http::response([], 500),
            'api.curseforge.com/v1/mods/search*searchFilter=test-error*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'Test Mod',
                        'slug' => 'test-mod',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=test-error");
        $response->assertStatus(200);
        // Should fall back to general search

        // Test error in searchMods (covers lines 265-271)
        Http::fake([
            'api.curseforge.com/v1/mods/search*' => Http::response([], 500),
            'api.modrinth.com/v2/search*' => Http::response([], 500),
            'api.modrinth.com/v2/project/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=test");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    /**
     * Test that searching mods requires authentication.
     */
    public function test_searching_mods_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/search-mods", [
            'query' => 'jei',
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot search mods for other user's mod pack.
     */
    public function test_user_cannot_search_mods_for_other_user_mod_pack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods", [
            'query' => 'jei',
        ]);

        $response->assertNotFound();
    }

    /**
     * Test that searching mods requires query parameter.
     */
    public function test_searching_mods_requires_query(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods");

        $response->assertStatus(302);
        $response->assertSessionHasErrors('query');
    }

    /**
     * Test that searching mods by slug works.
     */
    public function test_searching_mods_by_slug_works(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=jei");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                [
                    'id' => 123456,
                    'name' => 'JEI',
                    'slug' => 'jei',
                ],
            ],
        ]);
    }

    /**
     * Test that searching mods falls back to general search when slug search doesn't match.
     */
    public function test_searching_mods_falls_back_to_general_search(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        // Mock: slug search returns null, general search returns results
        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=test-mod*' => Http::response([
                'data' => [], // Slug search returns empty
            ], 200),
            'api.curseforge.com/v1/mods/search*searchFilter=test-mod*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'Test Mod',
                        'slug' => 'test-mod',
                        'downloadCount' => 50000,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=test-mod");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);
        // Verify general search path is covered (lines 129-132)
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test searching mods with CurseForge URL containing slug (success case).
     * Covers ModPackController lines 127-149.
     */
    public function test_searching_mods_with_curseforge_url_by_slug_success(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=jei*' => Http::response([
                'data' => [
                    [
                        'id' => 123456,
                        'name' => 'JEI',
                        'slug' => 'jei',
                        'downloadCount' => 1000000,
                    ],
                ],
            ], 200),
        ]);

        $url = 'https://www.curseforge.com/minecraft/mc-mods/jei';
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".urlencode($url));

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                [
                    'id' => 123456,
                    'name' => 'JEI',
                    'slug' => 'jei',
                ],
            ],
        ]);
    }

    /**
     * Test searching mods with CurseForge URL containing slug (mod not found).
     * Covers ModPackController lines 127-149.
     */
    public function test_searching_mods_with_curseforge_url_by_slug_not_found(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*slug=nonexistent-slug*' => Http::response([
                'data' => [], // Mod not found
            ], 200),
            'api.curseforge.com/v1/mods/search*searchFilter=*' => Http::response([
                'data' => [
                    [
                        'id' => 789,
                        'name' => 'Some Other Mod',
                        'slug' => 'some-other-mod',
                    ],
                ],
            ], 200),
        ]);

        $url = 'https://www.curseforge.com/minecraft/mc-mods/nonexistent-slug';
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".urlencode($url));

        $response->assertStatus(200);
        // Should fall back to general search
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test searching mods with CurseForge URL containing mod_id (success case).
     * Covers ModPackController lines 150-166.
     */
    public function test_searching_mods_with_curseforge_url_by_mod_id_success(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456*' => Http::response([
                'data' => [
                    'id' => 123456,
                    'name' => 'JEI',
                    'slug' => 'jei',
                    'downloadCount' => 1000000,
                ],
            ], 200),
        ]);

        $url = 'https://www.curseforge.com/projects/123456';
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".urlencode($url));

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                [
                    'id' => 123456,
                    'name' => 'JEI',
                    'slug' => 'jei',
                ],
            ],
        ]);
    }

    /**
     * Test searching mods with CurseForge URL containing mod_id (mod not found).
     * Covers ModPackController lines 150-166.
     */
    public function test_searching_mods_with_curseforge_url_by_mod_id_not_found(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/999999*' => Http::response(['error' => 'Not found'], 404),
            'api.curseforge.com/v1/mods/search*searchFilter=*' => Http::response([
                'data' => [
                    [
                        'id' => 789,
                        'name' => 'Some Other Mod',
                        'slug' => 'some-other-mod',
                    ],
                ],
            ], 200),
        ]);

        $url = 'https://www.curseforge.com/projects/999999';
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".urlencode($url));

        $response->assertStatus(200);
        // Should fall back to general search
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test searching mods with CurseForge URL that cannot be parsed.
     * Covers ModPackController lines 168-171.
     */
    public function test_searching_mods_with_unparseable_curseforge_url(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*searchFilter=*' => Http::response([
                'data' => [
                    [
                        'id' => 789,
                        'name' => 'Some Mod',
                        'slug' => 'some-mod',
                    ],
                ],
            ], 200),
        ]);

        // URL that contains curseforge.com but doesn't match the expected pattern
        $url = 'https://www.curseforge.com/minecraft/invalid/path/123';
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".urlencode($url));

        $response->assertStatus(200);
        // Should fall back to general search since URL couldn't be parsed
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test searching mods with Modrinth URL by slug (success case).
     */
    public function test_searching_mods_with_modrinth_url_by_slug_success(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.modrinth.com/v2/project/test-mod' => Http::response([
                'id' => 'test-project-id',
                'slug' => 'test-mod',
                'title' => 'Test Mod',
                'description' => 'A test mod',
            ], 200),
        ]);

        $url = 'https://modrinth.com/mod/test-mod';
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=".urlencode($url));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('modrinth', $data[0]['_source'] ?? null);
    }

    /**
     * Test that searching mods returns results from both CurseForge and Modrinth.
     */
    public function test_searching_mods_returns_results_from_both_platforms(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/search*searchFilter=test*' => Http::response([
                'data' => [
                    [
                        'id' => 123,
                        'name' => 'Test Mod CF',
                        'slug' => 'test-mod-cf',
                    ],
                ],
            ], 200),
            'api.modrinth.com/v2/search*query=test*' => Http::response([
                'hits' => [
                    [
                        'project_id' => 'test-project-id',
                        'title' => 'Test Mod MR',
                        'slug' => 'test-mod-mr',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/search-mods?query=test");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // Should have results from both platforms
        $sources = array_column($data, '_source');
        $this->assertContains('curseforge', $sources);
        $this->assertContains('modrinth', $sources);
    }
}
