<?php

namespace Tests\Feature\Services;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\User;
use App\Services\ModPackExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModPackExportServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that exportAsCurseForge handles mods without CurseForge IDs.
     * Covers ModPackExportService exportAsCurseForge with missing CurseForge data.
     */
    public function test_export_as_curseforge_handles_mods_without_curseforge_ids(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        // Create mod without CurseForge ID
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Custom Mod',
            'curseforge_mod_id' => null,
            'curseforge_file_id' => null,
        ]);

        // Create mod with CurseForge ID
        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'CurseForge Mod',
            'curseforge_mod_id' => 123456,
            'curseforge_file_id' => 789012,
            'source' => 'curseforge',
        ]);

        // Mock download info
        Http::fake([
            '*' => Http::response('fake jar content', 200),
        ]);

        $service = new ModPackExportService;
        $zipPath = $service->exportAsCurseForge($modPack);

        $this->assertFileExists($zipPath);

        // Clean up
        @unlink($zipPath);
    }

    /**
     * Test that exportAsText generates correct text format.
     * Covers ModPackExportService exportAsText method.
     */
    public function test_export_as_text_generates_correct_format(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Pack',
            'description' => 'A test mod pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'source' => 'curseforge',
            'curseforge_slug' => 'test-mod',
        ]);

        $service = new ModPackExportService;
        $result = $service->exportAsText($modPack);

        $this->assertIsString($result);
        $this->assertStringContainsString('Test Pack', $result);
        $this->assertStringContainsString('A test mod pack', $result);
        $this->assertStringContainsString('Minecraft Version: 1.20.1', $result);
        $this->assertStringContainsString('Loader: Forge', $result);
        $this->assertStringContainsString('Test Mod', $result);
    }

    /**
     * Test that exportAsCsv generates correct CSV format.
     * Covers ModPackExportService exportAsCsv method.
     */
    public function test_export_as_csv_generates_correct_format(): void
    {
        $user = User::factory()->create();
        $modPack = ModPack::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Pack',
            'minecraft_version' => '1.20.1',
            'software' => 'forge',
        ]);

        ModPackItem::factory()->create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => 'Test Mod',
            'mod_version' => '1.0.0',
            'source' => 'curseforge',
            'curseforge_slug' => 'test-mod',
        ]);

        $service = new ModPackExportService;
        $result = $service->exportAsCsv($modPack);

        $this->assertIsString($result);
        $this->assertStringContainsString('"Name","Version","Source","Platform URL"', $result);
        $this->assertStringContainsString('"Test Mod","1.0.0","Curseforge"', $result);
        $this->assertStringContainsString('curseforge.com/minecraft/mc-mods/test-mod', $result);
    }

    /**
     * Test that getModUrl handles different mod sources.
     * Covers ModPackExportService getModUrl method.
     */
    public function test_get_mod_url_handles_different_sources(): void
    {
        $service = new ModPackExportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getModUrl');
        $method->setAccessible(true);

        // Test CurseForge mod
        $curseforgeItem = ModPackItem::factory()->make([
            'source' => 'curseforge',
            'curseforge_slug' => 'test-mod',
        ]);
        $result = $method->invoke($service, $curseforgeItem);
        $this->assertEquals('https://www.curseforge.com/minecraft/mc-mods/test-mod', $result);

        // Test Modrinth mod
        $modrinthItem = ModPackItem::factory()->make([
            'source' => 'modrinth',
            'modrinth_slug' => 'test-mod',
        ]);
        $result = $method->invoke($service, $modrinthItem);
        $this->assertEquals('https://modrinth.com/mod/test-mod', $result);

        // Test mod without source but with CurseForge slug
        $noSourceItem = ModPackItem::factory()->make([
            'source' => null,
            'curseforge_slug' => 'test-mod',
            'modrinth_slug' => null,
        ]);
        $result = $method->invoke($service, $noSourceItem);
        $this->assertEquals('https://www.curseforge.com/minecraft/mc-mods/test-mod', $result);

        // Test mod without any slugs
        $noSlugItem = ModPackItem::factory()->make([
            'source' => null,
            'curseforge_slug' => null,
            'modrinth_slug' => null,
        ]);
        $result = $method->invoke($service, $noSlugItem);
        $this->assertEquals('', $result);
    }

    /**
     * Test that mapLoaderToMultiMC handles different loaders.
     * Covers ModPackExportService mapLoaderToMultiMC method.
     */
    public function test_map_loader_to_multimc_handles_different_loaders(): void
    {
        $service = new ModPackExportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapLoaderToMultiMC');
        $method->setAccessible(true);

        $this->assertEquals('net.minecraftforge', $method->invoke($service, 'forge'));
        $this->assertEquals('net.fabricmc.fabric-loader', $method->invoke($service, 'fabric'));
        $this->assertEquals('org.quiltmc.quilt-loader', $method->invoke($service, 'quilt'));
        $this->assertEquals('net.neoforged', $method->invoke($service, 'neoforge'));
        $this->assertNull($method->invoke($service, 'unknown'));
    }

    /**
     * Test that mapLoaderToModrinth handles different loaders.
     * Covers ModPackExportService mapLoaderToModrinth method.
     */
    public function test_map_loader_to_modrinth_handles_different_loaders(): void
    {
        $service = new ModPackExportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapLoaderToModrinth');
        $method->setAccessible(true);

        $this->assertEquals('neoforge', $method->invoke($service, 'neoforge'));
        $this->assertEquals('forge', $method->invoke($service, 'forge'));
        $this->assertEquals('fabric', $method->invoke($service, 'fabric'));
    }

    /**
     * Test that extractLoaderVersion returns appropriate versions.
     * Covers ModPackExportService extractLoaderVersion method.
     */
    public function test_extract_loader_version_returns_appropriate_versions(): void
    {
        $service = new ModPackExportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractLoaderVersion');
        $method->setAccessible(true);

        // Test 1.20.1 versions
        $this->assertEquals('47.1.0', $method->invoke($service, 'forge', '1.20.1'));
        $this->assertEquals('0.14.22', $method->invoke($service, 'fabric', '1.20.1'));
        $this->assertEquals('0.23.0', $method->invoke($service, 'quilt', '1.20.1'));
        $this->assertEquals('20.1.0', $method->invoke($service, 'neoforge', '1.20.1'));

        // Test 1.19.2 versions
        $this->assertEquals('43.2.0', $method->invoke($service, 'forge', '1.19.2'));
        $this->assertEquals('0.14.10', $method->invoke($service, 'fabric', '1.19.2'));

        // Test unknown version
        $this->assertEquals('latest', $method->invoke($service, 'forge', '1.21.0'));
        $this->assertEquals('latest', $method->invoke($service, 'unknown', '1.20.1'));
    }

    /**
     * Test that escapeCsv handles special characters correctly.
     * Covers ModPackExportService escapeCsv method.
     */
    public function test_escape_csv_handles_special_characters(): void
    {
        $service = new ModPackExportService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('escapeCsv');
        $method->setAccessible(true);

        $this->assertEquals('test', $method->invoke($service, 'test'));
        $this->assertEquals('test""quote', $method->invoke($service, 'test"quote'));
        $this->assertEquals('test""""double', $method->invoke($service, 'test""double'));
    }
}
