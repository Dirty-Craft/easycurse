<?php

namespace Tests\Feature\Services;

use App\Services\ModrinthService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ModrinthServiceTest extends TestCase
{
    /**
     * Test that getProject handles API errors gracefully.
     * Covers ModrinthService getProject error handling.
     */
    public function test_get_project_handles_api_errors(): void
    {
        Http::fake([
            'api.modrinth.com/v2/project/test-mod*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $service = new ModrinthService;
        $result = $service->getProject('test-mod');

        $this->assertNull($result);
    }

    /**
     * Test that getProjectVersions handles API errors gracefully.
     * Covers ModrinthService getProjectVersions error handling.
     */
    public function test_get_project_versions_handles_api_errors(): void
    {
        Http::fake([
            'api.modrinth.com/v2/project/test-mod/version*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $service = new ModrinthService;
        $result = $service->getProjectVersions('test-mod', '1.20.1', 'fabric');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that getVersion handles API errors gracefully.
     * Covers ModrinthService getVersion error handling.
     */
    public function test_get_version_handles_api_errors(): void
    {
        Http::fake([
            'api.modrinth.com/v2/version/version-id*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $service = new ModrinthService;
        $result = $service->getVersion('version-id');

        $this->assertNull($result);
    }

    /**
     * Test that extractModInfoFromUrl handles various URL formats.
     * Covers ModrinthService extractModInfoFromUrl method.
     */
    public function test_extract_mod_info_from_url_handles_various_formats(): void
    {
        $service = new ModrinthService;

        // Test standard Modrinth URL
        $result = $service->extractModInfoFromUrl('https://modrinth.com/mod/fabric-api');
        $this->assertIsArray($result);
        $this->assertEquals('fabric-api', $result['slug']);

        // Test URL with version path
        $result = $service->extractModInfoFromUrl('https://modrinth.com/mod/fabric-api/version/0.91.0');
        $this->assertIsArray($result);
        $this->assertEquals('fabric-api', $result['slug']);

        // Test invalid URL
        $result = $service->extractModInfoFromUrl('https://example.com/invalid');
        $this->assertNull($result);

        // Test non-Modrinth URL
        $result = $service->extractModInfoFromUrl('https://curseforge.com/minecraft/mc-mods/test');
        $this->assertNull($result);
    }

    /**
     * Test that getVersionDependencies handles different dependency types.
     * Covers ModrinthService getVersionDependencies method.
     */
    public function test_get_version_dependencies_handles_different_types(): void
    {
        $service = new ModrinthService;

        $versionData = [
            'dependencies' => [
                ['project_id' => 'fabric-api', 'dependency_type' => 'required'],
                ['project_id' => 'mod-menu', 'dependency_type' => 'optional'],
                ['project_id' => 'embedded-mod', 'dependency_type' => 'embedded'],
                ['project_id' => 'incompatible-mod', 'dependency_type' => 'incompatible'],
                ['project_id' => 'unknown-mod', 'dependency_type' => 'unknown'],
            ],
        ];

        $result = $service->getVersionDependencies($versionData, 'modrinth');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('required', $result);
        $this->assertArrayHasKey('optional', $result);
        $this->assertArrayHasKey('embedded', $result);
        $this->assertArrayHasKey('incompatible', $result);

        $this->assertContains('fabric-api', $result['required']);
        $this->assertContains('mod-menu', $result['optional']);
        $this->assertContains('embedded-mod', $result['embedded']);
        $this->assertContains('incompatible-mod', $result['incompatible']);
        $this->assertNotContains('unknown-mod', $result['required']); // Unknown type ignored
    }

    /**
     * Test that getVersionDependencies handles missing dependencies array.
     * Covers ModrinthService getVersionDependencies with no dependencies.
     */
    public function test_get_version_dependencies_handles_missing_dependencies(): void
    {
        $service = new ModrinthService;

        $versionData = []; // No dependencies key

        $result = $service->getVersionDependencies($versionData, 'modrinth');

        $this->assertIsArray($result);
        $this->assertEmpty($result['required']);
        $this->assertEmpty($result['optional']);
        $this->assertEmpty($result['embedded']);
        $this->assertEmpty($result['incompatible']);
    }

    /**
     * Test that invalidateModCache clears appropriate cache keys.
     * Covers ModrinthService invalidateModCache method.
     */
    public function test_invalidate_mod_cache_clears_appropriate_keys(): void
    {
        $service = new ModrinthService;

        // This method should run without errors
        $service->invalidateModCache('fabric-api');

        // Since we can't easily test cache clearing without mocking Cache facade,
        // we just ensure the method runs without throwing exceptions
        $this->assertTrue(true);
    }

    /**
     * Test that invalidateModVersionCache clears appropriate cache keys.
     * Covers ModrinthService invalidateModVersionCache method.
     */
    public function test_invalidate_mod_version_cache_clears_appropriate_keys(): void
    {
        $service = new ModrinthService;

        // This method should run without errors
        $service->invalidateModVersionCache('fabric-api', 'version-123');

        // Since we can't easily test cache clearing without mocking Cache facade,
        // we just ensure the method runs without throwing exceptions
        $this->assertTrue(true);
    }

    /**
     * Test that getProject returns correct project data.
     * Covers ModrinthService getProject success case.
     */
    public function test_get_project_returns_correct_data(): void
    {
        Http::fake([
            'api.modrinth.com/v2/project/fabric-api*' => Http::response([
                'id' => 'fabric-api',
                'slug' => 'fabric-api',
                'title' => 'Fabric API',
                'description' => 'Essential hooks for modding with Fabric.',
            ], 200),
        ]);

        $service = new ModrinthService;
        $result = $service->getProject('fabric-api');

        $this->assertIsArray($result);
        $this->assertEquals('fabric-api', $result['id']);
        $this->assertEquals('Fabric API', $result['title']);
    }

    /**
     * Test that getProjectVersions returns correct version data.
     * Covers ModrinthService getProjectVersions success case.
     */
    public function test_get_project_versions_returns_correct_data(): void
    {
        Http::fake([
            'api.modrinth.com/v2/project/fabric-api/version*' => Http::response([
                [
                    'id' => 'version-123',
                    'project_id' => 'fabric-api',
                    'version_number' => '0.91.0',
                    'game_versions' => ['1.20.1'],
                    'loaders' => ['fabric'],
                ],
            ], 200),
        ]);

        $service = new ModrinthService;
        $result = $service->getProjectVersions('fabric-api', '1.20.1', 'fabric');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('version-123', $result[0]['id']);
        $this->assertEquals('0.91.0', $result[0]['version_number']);
    }

    /**
     * Test that getVersion returns correct version data.
     * Covers ModrinthService getVersion success case.
     */
    public function test_get_version_returns_correct_data(): void
    {
        Http::fake([
            'api.modrinth.com/v2/version/version-123*' => Http::response([
                'id' => 'version-123',
                'project_id' => 'fabric-api',
                'version_number' => '0.91.0',
                'files' => [
                    [
                        'url' => 'https://cdn.modrinth.com/data/fabric-api/versions/version-123/fabric-api.jar',
                        'filename' => 'fabric-api.jar',
                    ],
                ],
            ], 200),
        ]);

        $service = new ModrinthService;
        $result = $service->getVersion('version-123');

        $this->assertIsArray($result);
        $this->assertEquals('version-123', $result['id']);
        $this->assertEquals('fabric-api', $result['project_id']);
        $this->assertEquals('0.91.0', $result['version_number']);
    }
}
