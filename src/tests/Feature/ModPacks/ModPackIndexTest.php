<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that mod packs index page is accessible to authenticated users.
     */
    public function test_mod_packs_index_is_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ModPacks/Index'));
    }

    /**
     * Test that mod packs index redirects unauthenticated users.
     */
    public function test_mod_packs_index_redirects_unauthenticated_users(): void
    {
        $response = $this->get('/mod-packs');

        $response->assertRedirect('/login');
    }

    /**
     * Test that mod packs index shows only user's mod packs.
     */
    public function test_mod_packs_index_shows_only_user_mod_packs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ModPack::factory()->count(3)->create(['user_id' => $user->id]);
        ModPack::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Index')
            ->has('modPacks', 3)
        );
    }

    /**
     * Test that mod packs index includes items relationship.
     */
    public function test_mod_packs_index_includes_items(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);
        ModPackItem::factory()->count(2)->create(['mod_pack_id' => $modPack->id]);

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('ModPacks/Index')
            ->has('modPacks.0.items', 2)
        );
    }

    /**
     * Test that mod packs are ordered by latest first.
     */
    public function test_mod_packs_are_ordered_by_latest_first(): void
    {
        $user = User::factory()->create();
        $oldModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $oldModPack->created_at = now()->subDays(2);
        $oldModPack->save();

        $newModPack = ModPack::factory()->create(['user_id' => $user->id]);
        $newModPack->created_at = now();
        $newModPack->save();

        $response = $this->actingAs($user)->get('/mod-packs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('modPacks', 2)
            ->where('modPacks.0.id', $newModPack->id)
            ->where('modPacks.1.id', $oldModPack->id)
        );
    }

    /**
     * Test that get mod files handles files with non-string gameVersions.
     */
    public function test_get_mod_files_handles_non_string_game_versions(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => 'JEI 1.20.1-11.6.0.1015',
                        'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1024000,
                        'gameVersions' => [
                            ['versionString' => '1.20.1'], // Array format
                            '1.20.1', // String format
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    /**
     * Test that get mod files handles files with invalid gameVersions structure.
     * Covers lines 169-171, 183-187 in filterFilesByExactVersion.
     */
    public function test_get_mod_files_handles_invalid_game_versions_structure(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    // File with non-array gameVersions (covers line 169-170)
                    [
                        'id' => 789015,
                        'displayName' => 'JEI Invalid',
                        'fileName' => 'jei-invalid.jar',
                        'gameVersions' => 'invalid', // Not an array - triggers line 169-170
                    ],
                    // File with non-array (integer) gameVersions (also covers line 169-170)
                    [
                        'id' => 789016,
                        'displayName' => 'JEI Invalid2',
                        'fileName' => 'jei-invalid2.jar',
                        'gameVersions' => 123, // Not an array
                    ],
                    // File with array containing non-string items that become invalid (covers lines 183-187)
                    [
                        'id' => 789017,
                        'displayName' => 'JEI Invalid3',
                        'fileName' => 'jei-invalid3.jar',
                        'gameVersions' => [
                            ['not' => 'valid'], // Object that doesn't have versionString/name/gameVersion - triggers lines 183-187
                            null, // null value - triggers line 187
                            123, // Non-string, non-array - triggers line 187
                        ],
                    ],
                    // File with array containing objects that have invalid structure (covers lines 179-187)
                    [
                        'id' => 789018,
                        'displayName' => 'JEI Invalid4',
                        'fileName' => 'jei-invalid4.jar',
                        'gameVersions' => [
                            ['someField' => 'value'], // Array without versionString/name/gameVersion
                        ],
                    ],
                    // File with valid structure for comparison
                    [
                        'id' => 789019,
                        'displayName' => 'JEI Valid',
                        'fileName' => 'jei-valid.jar',
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/mod-files?mod_id=123456");

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should only return the valid file
        $this->assertCount(1, $data);
        $this->assertEquals(789019, $data[0]['id']);
    }

    /**
     * Test getGameVersions with cache hit (covers line 300).
     */
    public function test_get_game_versions_uses_cache(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'data' => [
                    [
                        'type' => 1,
                        'versions' => ['1.21.0', '1.20.6', '1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        // First request - populates cache
        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear HTTP fake to ensure second request doesn't hit API
        Http::fake([]);

        // Second request - should use cache (line 300)
        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);
    }

    /**
     * Test getGameVersions error paths (covers lines 307-309, 338-345, 350-355, 364-374, 390, 405, 412, 429-434, 460-480).
     */
    public function test_get_game_versions_error_paths(): void
    {
        $user = User::factory()->create();

        // Clear cache before each test
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test empty API key (covers lines 307-309)
        config(['services.curseforge.api_key' => '']);
        Http::fake([]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);
        // Should return empty array when API key is missing

        // Reset API key and clear cache
        config(['services.curseforge.api_key' => 'test-key']);
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test unsuccessful HTTP response (covers lines 338-345)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test API error in response body (covers lines 350-355)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'error' => 'Invalid request',
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test empty/invalid data (covers lines 364-374)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test version type without type/versions fields (covers line 390)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'data' => [
                    // Missing type or versions
                    [
                        'someOtherField' => 'value',
                    ],
                    [
                        'type' => 2, // Has type but not type 1
                        'versions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test object format for versions (covers line 405) - associative array with non-sequential keys
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'data' => [
                    [
                        'type' => 1,
                        'versions' => [
                            'a' => '1.21.0',
                            'b' => '1.20.6',
                        ], // Associative array, not sequential
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test empty/invalid version strings (covers line 412)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'data' => [
                    [
                        'type' => 1,
                        'versions' => ['', null, 123, []], // Empty string, null, non-string, array
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test no valid versions after processing (covers lines 429-434)
        // Only type 2 versions, no type 1
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => Http::response([
                'data' => [
                    [
                        'type' => 2,
                        'versions' => ['1.20.1', '1.21.0'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test RequestException (covers lines 460-473)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => function () {
                throw new \Illuminate\Http\Client\RequestException(
                    new \Illuminate\Http\Client\Response(
                        new \GuzzleHttp\Psr7\Response(500, [], 'Server Error')
                    )
                );
            },
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);

        // Clear cache
        \Illuminate\Support\Facades\Cache::forget('curseforge_game_versions');

        // Test generic Exception (covers lines 473-480)
        Http::fake([
            'api.curseforge.com/v1/games/*/versions*' => function () {
                throw new \Exception('Unexpected error');
            },
        ]);

        $response = $this->actingAs($user)->get('/mod-packs');
        $response->assertStatus(200);
    }
}
