<?php

namespace Tests\Feature\ModPacks;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModPackExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure temp directory exists
        Storage::makeDirectory('temp');
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $tempDir = storage_path('app/temp');
        if (is_dir($tempDir)) {
            $files = glob($tempDir.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                } elseif (is_dir($file)) {
                    $this->deleteDirectory($file);
                }
            }
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * Test that user can export modpack as CurseForge format.
     */
    public function test_user_can_export_modpack_as_curseforge(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $item1 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'source' => 'curseforge',
        ]);

        $item2 = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Applied Energistics 2',
            'mod_version' => '15.0.7',
            'curseforge_mod_id' => 223794,
            'curseforge_file_id' => 4639210,
            'source' => 'curseforge',
        ]);

        // Mock CurseForge API responses
        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256' => Http::response([
                'data' => [
                    'id' => 4638256,
                    'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/4638256/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/mods/223794/files/4639210' => Http::response([
                'data' => [
                    'id' => 4639210,
                    'fileName' => 'appliedenergistics2-forge-15.0.7.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4639/210/appliedenergistics2-forge-15.0.7.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/4639210/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4639/210/appliedenergistics2-forge-15.0.7.jar',
                ],
            ], 200),
            'mediafilez.forgecdn.net/files/4638/256/*' => Http::response('fake jar content', 200, ['Content-Type' => 'application/java-archive']),
            'mediafilez.forgecdn.net/files/4639/210/*' => Http::response('fake jar content', 200, ['Content-Type' => 'application/java-archive']),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/curseforge");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), 'Test_Modpack-curseforge.zip'),
            'Content-Disposition header should contain filename'
        );

        // Verify downloads_count was incremented
        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that user can export modpack as MultiMC format.
     */
    public function test_user_can_export_modpack_as_multimc(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'source' => 'curseforge',
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256' => Http::response([
                'data' => [
                    'id' => 4638256,
                    'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/4638256/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'mediafilez.forgecdn.net/files/4638/256/*' => Http::response('fake jar content', 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/multimc");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), 'Test_Modpack-multimc.zip'),
            'Content-Disposition header should contain filename'
        );

        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that user can export modpack as Modrinth format.
     */
    public function test_user_can_export_modpack_as_modrinth(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Fabric API',
            'mod_version' => '0.91.0',
            'modrinth_project_id' => 'P7dR8mSH',
            'modrinth_version_id' => 'version123',
            'source' => 'modrinth',
        ]);

        Http::fake([
            'api.modrinth.com/v2/version/version123' => Http::response([
                'id' => 'version123',
                'project_id' => 'P7dR8mSH',
                'version_number' => '0.91.0',
                'files' => [
                    [
                        'hashes' => [
                            'sha1' => 'abc123',
                            'sha512' => 'def456',
                        ],
                        'url' => 'https://cdn.modrinth.com/data/P7dR8mSH/versions/version123/fabric-api-0.91.0.jar',
                        'filename' => 'fabric-api-0.91.0.jar',
                        'size' => 12345,
                    ],
                ],
            ], 200),
            'cdn.modrinth.com/*' => Http::response('fake jar content', 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/modrinth");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/x-modrinth-modpack+zip');
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), 'Test_Modpack.mrpack'),
            'Content-Disposition header should contain filename'
        );

        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that user can export modpack as text format.
     */
    public function test_user_can_export_modpack_as_text(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
            'description' => 'A test modpack',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'curseforge_slug' => 'jei',
            'source' => 'curseforge',
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/text");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), 'Test_Modpack.txt'),
            'Content-Disposition header should contain filename'
        );

        $content = $response->getContent();
        $this->assertStringContainsString('Test Modpack', $content);
        $this->assertStringContainsString('JEI', $content);
        $this->assertStringContainsString('1.20.1', $content);
        $this->assertStringContainsString('Forge', $content);

        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that user can export modpack as CSV format.
     */
    public function test_user_can_export_modpack_as_csv(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'curseforge_slug' => 'jei',
            'source' => 'curseforge',
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/csv");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), 'Test_Modpack.csv'),
            'Content-Disposition header should contain filename'
        );

        $content = $response->getContent();
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('Version', $content);
        $this->assertStringContainsString('Source', $content);
        $this->assertStringContainsString('JEI', $content);
        $this->assertStringContainsString('Curseforge', $content);

        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that export requires authentication.
     */
    public function test_export_requires_authentication(): void
    {
        $modPack = ModPack::factory()->create();

        $response = $this->get("/mod-packs/{$modPack->id}/export/curseforge");

        $response->assertRedirect('/login');
    }

    /**
     * Test that user cannot export other user's modpack.
     */
    public function test_user_cannot_export_other_user_modpack(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/curseforge");

        $response->assertNotFound();
    }

    /**
     * Test that invalid export format returns error.
     */
    public function test_invalid_export_format_returns_error(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/invalid");

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Unsupported export format.',
        ]);
    }

    /**
     * Test that shared modpack can be exported without authentication.
     */
    public function test_shared_modpack_can_be_exported_without_authentication(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        $item = ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'source' => 'curseforge',
        ]);

        $token = $modPack->generateShareToken();

        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256' => Http::response([
                'data' => [
                    'id' => 4638256,
                    'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/4638256/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'mediafilez.forgecdn.net/files/4638/256/*' => Http::response('fake jar content', 200),
        ]);

        $response = $this->get("/shared/{$token}/export/text");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));

        $modPack->refresh();
        $this->assertEquals(1, $modPack->downloads_count);
    }

    /**
     * Test that invalid share token for export returns 404.
     */
    public function test_invalid_share_token_for_export_returns_404(): void
    {
        $response = $this->get('/shared/invalid-token-12345/export/text');

        $response->assertNotFound();
    }

    /**
     * Test that export handles mods without download info gracefully.
     */
    public function test_export_handles_mods_without_download_info(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Item with CurseForge data
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'JEI',
            'mod_version' => '11.6.0.1015',
            'curseforge_mod_id' => 238222,
            'curseforge_file_id' => 4638256,
            'source' => 'curseforge',
        ]);

        // Item without download metadata
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'mod_version' => '1.0.0',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
            'source' => null,
        ]);

        Http::fake([
            'api.curseforge.com/v1/mods/238222/files/4638256' => Http::response([
                'data' => [
                    'id' => 4638256,
                    'fileName' => 'jei-1.20.1-11.6.0.1015.jar',
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'api.curseforge.com/v1/files/4638256/download-url' => Http::response([
                'data' => [
                    'downloadUrl' => 'https://mediafilez.forgecdn.net/files/4638/256/jei-1.20.1-11.6.0.1015.jar',
                ],
            ], 200),
            'mediafilez.forgecdn.net/files/4638/256/*' => Http::response('fake jar content', 200),
        ]);

        // Text export should work even with missing download info
        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/text");

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('JEI', $content);
        $this->assertStringContainsString('Custom Mod', $content);
    }

    /**
     * Test that export works with Modrinth mods.
     */
    public function test_export_works_with_modrinth_mods(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Modpack',
            'minecraft_version' => '1.20.1',
            'software' => 'fabric',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Fabric API',
            'mod_version' => '0.91.0',
            'modrinth_project_id' => 'P7dR8mSH',
            'modrinth_version_id' => 'version123',
            'modrinth_slug' => 'fabric-api',
            'source' => 'modrinth',
        ]);

        Http::fake([
            'api.modrinth.com/v2/version/version123' => Http::response([
                'id' => 'version123',
                'project_id' => 'P7dR8mSH',
                'version_number' => '0.91.0',
                'files' => [
                    [
                        'hashes' => [
                            'sha1' => 'abc123',
                            'sha512' => 'def456',
                        ],
                        'url' => 'https://cdn.modrinth.com/data/P7dR8mSH/versions/version123/fabric-api-0.91.0.jar',
                        'filename' => 'fabric-api-0.91.0.jar',
                        'size' => 12345,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->get("/mod-packs/{$modPack->id}/export/text");

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('Fabric API', $content);
        $this->assertStringContainsString('Modrinth', $content);
    }
}
