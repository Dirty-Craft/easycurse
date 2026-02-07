<?php

namespace Tests\Feature\Services;

use App\Services\CurseForgeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurseForgeServiceTest extends TestCase
{
    /**
     * Test that getModFiles handles API errors gracefully.
     * Covers CurseForgeService getModFiles error handling.
     */
    public function test_get_mod_files_handles_api_errors(): void
    {
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $service = new CurseForgeService;
        $result = $service->getModFiles(123456, '1.20.1', 'forge');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that getModFile handles API errors gracefully.
     * Covers CurseForgeService getModFile error handling.
     */
    public function test_get_mod_file_handles_api_errors(): void
    {
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files/789012*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $service = new CurseForgeService;
        $result = $service->getModFile(123456, 789012);

        $this->assertNull($result);
    }

    /**
     * Test that searchModBySlug handles API errors gracefully.
     * Covers CurseForgeService searchModBySlug error handling.
     */
    public function test_search_mod_by_slug_handles_api_errors(): void
    {
        Http::fake([
            'api.curseforge.com/v1/mods/search*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $service = new CurseForgeService;
        $result = $service->searchModBySlug('test-mod');

        $this->assertNull($result);
    }

    /**
     * Test that extractModInfoFromUrl handles various URL formats.
     * Covers CurseForgeService extractModInfoFromUrl method.
     */
    public function test_extract_mod_info_from_url_handles_various_formats(): void
    {
        $service = new CurseForgeService;

        // Test standard CurseForge URL
        $result = $service->extractModInfoFromUrl('https://www.curseforge.com/minecraft/mc-mods/jei');
        $this->assertIsArray($result);
        $this->assertEquals('jei', $result['slug']);

        // Test URL with files path
        $result = $service->extractModInfoFromUrl('https://www.curseforge.com/minecraft/mc-mods/jei/files');
        $this->assertIsArray($result);
        $this->assertEquals('jei', $result['slug']);

        // Test invalid URL
        $result = $service->extractModInfoFromUrl('https://example.com/invalid');
        $this->assertNull($result);

        // Test non-CurseForge URL
        $result = $service->extractModInfoFromUrl('https://modrinth.com/mod/test');
        $this->assertNull($result);
    }

    /**
     * Test that getFileDependencies handles different dependency types.
     * Covers CurseForgeService getFileDependencies method.
     */
    public function test_get_file_dependencies_handles_different_types(): void
    {
        $service = new CurseForgeService;

        $fileData = [
            'dependencies' => [
                ['modId' => 111, 'relationType' => 3], // Required
                ['modId' => 222, 'relationType' => 2], // Optional
                ['modId' => 333, 'relationType' => 1], // Embedded
                ['modId' => 444, 'relationType' => 4], // Incompatible
                ['modId' => 555, 'relationType' => 99], // Unknown type
            ],
        ];

        $result = $service->getFileDependencies($fileData, 'curseforge');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('required', $result);
        $this->assertArrayHasKey('optional', $result);
        $this->assertArrayHasKey('embedded', $result);
        $this->assertArrayHasKey('incompatible', $result);

        $this->assertContains(111, $result['required']);
        $this->assertContains(222, $result['optional']);
        $this->assertContains(333, $result['embedded']);
        $this->assertContains(444, $result['incompatible']);
        $this->assertNotContains(555, $result['required']); // Unknown type ignored
    }

    /**
     * Test that getFileDependencies handles missing dependencies array.
     * Covers CurseForgeService getFileDependencies with no dependencies.
     */
    public function test_get_file_dependencies_handles_missing_dependencies(): void
    {
        $service = new CurseForgeService;

        $fileData = []; // No dependencies key

        $result = $service->getFileDependencies($fileData, 'curseforge');

        $this->assertIsArray($result);
        $this->assertEmpty($result['required']);
        $this->assertEmpty($result['optional']);
        $this->assertEmpty($result['embedded']);
        $this->assertEmpty($result['incompatible']);
    }

    /**
     * Test that getLatestModFile returns correct file.
     * Covers CurseForgeService getLatestModFile method.
     */
    public function test_get_latest_mod_file_returns_correct_file(): void
    {
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [
                    [
                        'id' => 789012,
                        'displayName' => '1.20.1-1.0.0',
                        'fileName' => 'test-mod-1.20.1-1.0.0.jar',
                        'gameVersions' => ['1.20.1'],
                        'fileDate' => '2024-01-01T00:00:00Z',
                    ],
                ],
            ], 200),
        ]);

        $service = new CurseForgeService;
        $result = $service->getLatestModFile(123456, '1.20.1', 'forge');

        $this->assertIsArray($result);
        $this->assertEquals(789012, $result['id']);
        $this->assertEquals('1.20.1-1.0.0', $result['displayName']);
    }

    /**
     * Test that getLatestModFile handles no compatible files.
     * Covers CurseForgeService getLatestModFile when no files match.
     */
    public function test_get_latest_mod_file_handles_no_compatible_files(): void
    {
        Http::fake([
            'api.curseforge.com/v1/mods/123456/files*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $service = new CurseForgeService;
        $result = $service->getLatestModFile(123456, '1.20.1', 'forge');

        $this->assertNull($result);
    }
}
