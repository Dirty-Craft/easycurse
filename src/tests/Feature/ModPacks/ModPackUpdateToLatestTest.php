<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackUpdateToLatestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user can update all mod items to latest version.
     */
    public function test_user_can_update_all_items_to_latest(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 123456,
        ]);

        // Mock CurseForge API responses
        Http::fake([
            'api.curseforge.com/v1/mods/*/files*' => Http::response([
                'data' => [
                    [
                        'id' => 999999,
                        'displayName' => '1.20.1-11.6.0.1017',
                        'fileName' => 'jei-1.20.1-11.6.0.1017.jar',
                        'fileDate' => '2024-01-01T00:00:00Z',
                        'fileLength' => 1000000,
                        'gameVersions' => ['1.20.1'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(
            "/mod-packs/{$modPack->id}/items/update-all-to-latest"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertArrayHasKey('updated_count', $response->json());
        $this->assertArrayHasKey('failed_count', $response->json());
    }
}
