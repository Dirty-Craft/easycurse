<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurseForgeService
{
    private string $baseUrl;

    private string $apiKey;

    private string $minecraftGameId;

    private string $minecraftModsClassId;

    public function __construct()
    {
        $this->baseUrl = config('services.curseforge.base_url');
        $this->apiKey = config('services.curseforge.api_key');
        $this->minecraftGameId = config('services.curseforge.minecraft_game_id');
        $this->minecraftModsClassId = config('services.curseforge.minecraft_mods_class_id');
    }

    /**
     * Get the HTTP client with authentication headers.
     */
    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Search for mods by slug.
     */
    public function searchModBySlug(string $slug): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.'mods/search', [
                'gameId' => $this->minecraftGameId,
                'classId' => $this->minecraftModsClassId,
                'slug' => $slug,
            ]);

            $response->throw();

            $data = $response->json('data');

            return ! empty($data) ? $data[0] : null;
        } catch (RequestException $e) {
            Log::error('CurseForge API error searching mod by slug', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get mod details by ID.
     */
    public function getMod(int $modId): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.'mods/'.$modId);

            $response->throw();

            return $response->json('data');
        } catch (RequestException $e) {
            Log::error('CurseForge API error getting mod', [
                'mod_id' => $modId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get files for a mod.
     */
    public function getModFiles(int $modId, ?string $gameVersion = null, ?string $software = null): array
    {
        try {
            $queryParams = [];

            if ($gameVersion) {
                $queryParams['gameVersion'] = $gameVersion;
            }

            if ($software) {
                // Map software string to CurseForge mod loader type
                // 1 = Forge, 4 = Fabric, 2 = Quilt
                $modLoaderType = match (strtolower($software)) {
                    'forge' => 1,
                    'fabric' => 4,
                    'quilt' => 2,
                    default => null,
                };

                if ($modLoaderType) {
                    $queryParams['modLoaderType'] = $modLoaderType;
                }
            }

            $response = $this->client()->get($this->baseUrl.'mods/'.$modId.'/files', $queryParams);

            $response->throw();

            $files = $response->json('data', []);

            // If a specific game version was requested, filter results to ensure strict matching
            // CurseForge API may return files for "1.20.1" when requesting "1.20"
            // We need to check each file's gameVersions array for an exact match
            if ($gameVersion && ! empty($files)) {
                $files = $this->filterFilesByExactVersion($files, $gameVersion);
            }

            return $files;
        } catch (RequestException $e) {
            Log::error('CurseForge API error getting mod files', [
                'mod_id' => $modId,
                'game_version' => $gameVersion,
                'software' => $software,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Filter files to only include those that support the exact game version.
     * This ensures strict version matching (e.g., "1.20" != "1.20.1").
     *
     * @param  array  $files  Array of file data from CurseForge API
     * @param  string  $requestedVersion  The exact Minecraft version to match
     * @return array Filtered array of files
     */
    private function filterFilesByExactVersion(array $files, string $requestedVersion): array
    {
        // Normalize the requested version for comparison
        $normalizedRequested = $this->normalizeVersion($requestedVersion);

        $filtered = [];

        foreach ($files as $file) {
            // CurseForge API may return gameVersions in different formats
            // Try multiple possible field names
            $gameVersions = $file['gameVersions']
                ?? $file['gameVersion']
                ?? [];

            // If gameVersion is a single string, convert to array
            if (is_string($gameVersions)) {
                $gameVersions = [$gameVersions];
            }

            // If it's not an array, skip this file
            if (! is_array($gameVersions)) {
                continue;
            }

            // Check if any of the file's game versions exactly match the requested version
            $hasExactMatch = false;
            foreach ($gameVersions as $fileVersion) {
                // Skip if not a string (could be an object/array in some API responses)
                if (! is_string($fileVersion)) {
                    // If it's an array/object, try to extract the version string
                    if (is_array($fileVersion)) {
                        $fileVersion = $fileVersion['versionString']
                            ?? $fileVersion['name']
                            ?? $fileVersion['gameVersion']
                            ?? '';
                    }
                    // If still not a string, skip
                    if (! is_string($fileVersion)) {
                        continue;
                    }
                }

                // Normalize the file's version for comparison
                $normalizedFileVersion = $this->normalizeVersion($fileVersion);

                // Strict equality check - versions must match exactly
                if ($normalizedFileVersion === $normalizedRequested) {
                    $hasExactMatch = true;
                    break;
                }
            }

            if ($hasExactMatch) {
                $filtered[] = $file;
            }
        }

        return $filtered;
    }

    /**
     * Normalize a version string for comparison.
     * Removes any non-version characters and ensures consistent formatting.
     *
     * @param  string  $version  Version string (e.g., "1.20", "1.20.1", "1.20.1-Fabric")
     * @return string Normalized version string
     */
    private function normalizeVersion(string $version): string
    {
        // Remove any loader suffixes (e.g., "-Fabric", "-Forge")
        $version = preg_replace('/-[A-Za-z]+$/', '', $version);

        // Trim whitespace
        $version = trim($version);

        return $version;
    }

    /**
     * Get a specific mod file by ID.
     */
    public function getModFile(int $modId, int $fileId): ?array
    {
        try {
            $response = $this->client()->get($this->baseUrl.'mods/'.$modId.'/files/'.$fileId);

            $response->throw();

            return $response->json('data');
        } catch (RequestException $e) {
            Log::error('CurseForge API error getting mod file', [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for mods with various filters.
     */
    public function searchMods(array $filters = []): array
    {
        try {
            $queryParams = array_merge([
                'gameId' => $this->minecraftGameId,
                'classId' => $this->minecraftModsClassId,
            ], $filters);

            $response = $this->client()->get($this->baseUrl.'mods/search', $queryParams);

            $response->throw();

            return $response->json('data', []);
        } catch (RequestException $e) {
            Log::error('CurseForge API error searching mods', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get the latest file for a mod matching the game version and software.
     */
    public function getLatestModFile(int $modId, string $gameVersion, string $software): ?array
    {
        $files = $this->getModFiles($modId, $gameVersion, $software);

        if (empty($files)) {
            return null;
        }

        // Files are typically returned in descending order (newest first)
        // Return the first file (latest)
        return $files[0];
    }

    /**
     * Get game versions for Minecraft.
     * Results are cached for 24 hours to avoid excessive API calls.
     */
    public function getGameVersions(): array
    {
        // Check cache first - cache for 24 hours since game versions don't change frequently
        $cached = Cache::get('curseforge_game_versions');
        if ($cached !== null && is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        // Cache is empty or doesn't exist, fetch from API
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                Log::error('CurseForge API key is not configured');

                return [];
            }

            // CurseForge API v1 game versions endpoint
            // The correct endpoint is games/<game id>/versions
            $url = $this->baseUrl.'games/'.$this->minecraftGameId.'/versions';

            Log::debug('Fetching game versions from CurseForge API', [
                'url' => $url,
                'game_id' => $this->minecraftGameId,
                'has_api_key' => ! empty($this->apiKey),
            ]);

            // CurseForge API v1 game versions endpoint uses GET
            $response = $this->client()->get($url);

            $statusCode = $response->status();
            $responseBody = $response->json();

            Log::debug('CurseForge API response', [
                'status' => $statusCode,
                'has_data' => isset($responseBody['data']),
                'data_count' => isset($responseBody['data']) ? count($responseBody['data']) : 0,
                'response_keys' => array_keys($responseBody ?? []),
                'full_response' => $responseBody,
            ]);

            // Check for HTTP errors first
            if (! $response->successful()) {
                Log::error('CurseForge API request failed', [
                    'status' => $statusCode,
                    'url' => $url,
                    'response' => $responseBody,
                    'body' => $response->body(),
                ]);

                return [];
            }

            // Check for API errors in response
            if (isset($responseBody['error']) || isset($responseBody['errors'])) {
                Log::error('CurseForge API returned error', [
                    'error' => $responseBody['error'] ?? $responseBody['errors'] ?? null,
                    'response' => $responseBody,
                ]);

                return [];
            }

            // CurseForge API v1 returns an array of version type objects
            // Each object has a 'type' field and a 'versions' field
            // Type 1 appears to be the main Minecraft game versions
            $data = $responseBody['data'] ?? [];

            if (empty($data) || ! is_array($data)) {
                Log::warning('CurseForge API returned empty game versions', [
                    'response' => $responseBody,
                    'status' => $statusCode,
                    'response_structure' => [
                        'has_data_key' => isset($responseBody['data']),
                        'data_type' => isset($responseBody['data']) ? gettype($responseBody['data']) : 'none',
                        'data_keys' => isset($responseBody['data']) && is_array($responseBody['data']) ? array_keys($responseBody['data']) : [],
                    ],
                ]);

                return [];
            }

            // Log first item structure for debugging
            if (! empty($data[0])) {
                Log::debug('CurseForge game version structure', [
                    'first_item' => $data[0],
                    'keys' => array_keys($data[0]),
                ]);
            }

            // Extract versions from version type objects
            // Look for type 1 (main Minecraft versions) or collect all types
            $versions = [];
            foreach ($data as $versionType) {
                if (! isset($versionType['type']) || ! isset($versionType['versions'])) {
                    continue;
                }

                $typeId = $versionType['type'];
                $versionList = $versionType['versions'];

                // Handle both array and object formats for versions
                $versionStrings = [];
                if (is_array($versionList)) {
                    // Check if it's an associative array (object with numeric keys) or a simple array
                    if (isset($versionList[0]) || (is_array($versionList) && ! empty($versionList) && array_keys($versionList) === range(0, count($versionList) - 1))) {
                        // Simple array
                        $versionStrings = $versionList;
                    } else {
                        // Object with numeric keys - extract values
                        $versionStrings = array_values($versionList);
                    }
                }

                // Process each version string
                foreach ($versionStrings as $versionString) {
                    if (empty($versionString) || ! is_string($versionString)) {
                        continue;
                    }

                    // Filter to only include standard Minecraft versions (type 1)
                    // These are versions like "1.20.6", "1.21.2", etc.
                    if ($typeId === 1) {
                        $versions[] = [
                            'id' => null, // API doesn't provide individual version IDs in this format
                            'name' => $versionString,
                            'slug' => str_replace('.', '-', $versionString),
                            'type' => $typeId,
                        ];
                    }
                }
            }

            if (empty($versions)) {
                Log::warning('No valid versions found after processing', [
                    'raw_data_count' => count($data),
                    'first_item' => $data[0] ?? null,
                ]);

                return [];
            }

            // Sort versions - newest first using version_compare
            usort($versions, function ($a, $b) {
                // Remove any non-version characters for comparison
                $aVersion = preg_replace('/[^0-9.]/', '', $a['name']);
                $bVersion = preg_replace('/[^0-9.]/', '', $b['name']);

                return version_compare($bVersion, $aVersion);
            });

            Log::info('Successfully loaded game versions', [
                'count' => count($versions),
                'first' => $versions[0]['name'] ?? null,
                'last' => end($versions)['name'] ?? null,
            ]);

            $result = array_values($versions);

            // Only cache if we have valid data
            if (! empty($result)) {
                Cache::put('curseforge_game_versions', $result, 24 * 60 * 60);
            }

            return $result;
        } catch (RequestException $e) {
            $errorDetails = [
                'url' => $this->baseUrl.'games/'.$this->minecraftGameId.'/versions',
                'status' => $e->response?->status(),
                'error' => $e->getMessage(),
                'response_body' => $e->response?->body(),
                'response_json' => $e->response?->json(),
            ];

            Log::error('CurseForge API error getting game versions', $errorDetails);

            // Don't cache errors
            return [];
        } catch (\Exception $e) {
            Log::error('Unexpected error getting game versions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't cache errors
            return [];
        }
    }

    /**
     * Get available mod loaders.
     * Returns common mod loaders with their CurseForge modLoaderType IDs.
     */
    public function getModLoaders(): array
    {
        // CurseForge mod loader types:
        // 1 = Forge
        // 2 = Quilt
        // 4 = Fabric
        // 6 = NeoForge
        return [
            ['id' => 1, 'name' => 'Forge', 'slug' => 'forge'],
            ['id' => 4, 'name' => 'Fabric', 'slug' => 'fabric'],
            ['id' => 2, 'name' => 'Quilt', 'slug' => 'quilt'],
            ['id' => 6, 'name' => 'NeoForge', 'slug' => 'neoforge'],
        ];
    }

    /**
     * Get mod dependencies for a file.
     *
     * @param  array  $fileData  The file data from getModFile or getModFiles
     * @return array Array of dependency mod IDs with their relation types
     */
    public function getFileDependencies(array $fileData): array
    {
        $dependencies = $fileData['dependencies'] ?? [];

        $result = [
            'required' => [],
            'optional' => [],
            'embedded' => [],
        ];

        foreach ($dependencies as $dependency) {
            $modId = $dependency['modId'] ?? null;
            $relationType = $dependency['relationType'] ?? null;

            if (! $modId) {
                continue;
            }

            // Relation types: 1 = EmbeddedLibrary, 2 = OptionalDependency, 3 = RequiredDependency
            match ($relationType) {
                1 => $result['embedded'][] = $modId,
                2 => $result['optional'][] = $modId,
                3 => $result['required'][] = $modId,
                default => null,
            };
        }

        return $result;
    }

    /**
     * Get the download URL for a mod file.
     * Uses mediafilez.forgecdn.net pattern (same as Python script) which works better for client-side downloads.
     *
     * @param  int  $fileId  The CurseForge file ID
     * @param  string|null  $fileName  Optional file name for constructing URL if not in API response
     * @return string|null The direct download URL or null if unavailable
     */
    public function getFileDownloadUrl(int $fileId, ?string $fileName = null): ?string
    {
        try {
            // CurseForge CDN URL format: https://mediafilez.forgecdn.net/files/{first4}/{rest}/{filename}
            // File ID is split: first 4 characters as integer, rest as integer
            // Example: File ID 5846846 -> files/5846/846/filename.jar
            // This matches the Python script's approach

            $fileIdStr = (string) $fileId;

            // Split file ID: first 4 characters, then the rest
            $firstPart = substr($fileIdStr, 0, 4);
            $secondPart = substr($fileIdStr, 4);

            // Convert to integers (removes leading zeros from second part if any)
            $id1 = (int) $firstPart;
            $id2 = (int) $secondPart;

            // If we have a filename, use it; otherwise use a generic name
            $filename = $fileName ?? "file-{$fileId}.jar";

            // Handle special characters in filename (like + which needs to be %2B)
            // Match Python script behavior: only replace if %2B is not already present
            if (strpos($filename, '+') !== false && strpos($filename, '%2B') === false) {
                $filename = str_replace('+', '%2B', $filename);
            }

            return "https://mediafilez.forgecdn.net/files/{$id1}/{$id2}/{$filename}";
        } catch (\Exception $e) {
            Log::error('CurseForge error generating download URL', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the download URL from CurseForge API.
     *
     * @param  int  $fileId  The CurseForge file ID
     * @return string|null The download URL or null if unavailable
     */
    public function getFileDownloadUrlFromApi(int $fileId): ?string
    {
        try {
            // Try to get download URL from CurseForge API files endpoint
            // This endpoint might return a URL that works better with CORS
            $response = $this->client()->get($this->baseUrl.'files/'.$fileId.'/download-url');

            if ($response->successful()) {
                $data = $response->json('data');
                $url = $data['url'] ?? null;

                if ($url) {
                    Log::debug('Got download URL from API endpoint', [
                        'file_id' => $fileId,
                        'url' => $url,
                    ]);

                    return $url;
                }
            }
        } catch (\Exception $e) {
            Log::debug('CurseForge download URL endpoint not available or failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get download information for a mod file (including URL and filename).
     *
     * @param  int  $modId  The CurseForge mod ID
     * @param  int  $fileId  The CurseForge file ID
     * @return array|null Array with 'url' and 'filename' keys, or null if unavailable
     */
    public function getFileDownloadInfo(int $modId, int $fileId): ?array
    {
        try {
            $file = $this->getModFile($modId, $fileId);

            if (! $file) {
                Log::warning('CurseForge file not found', [
                    'mod_id' => $modId,
                    'file_id' => $fileId,
                ]);

                return null;
            }

            $fileName = $file['fileName'] ?? null;

            // Prioritize constructed mediafilez.forgecdn.net URL for better client-side compatibility
            // This matches the Python script's approach and works better for browser downloads
            $downloadUrl = $this->getFileDownloadUrl($fileId, $fileName);

            // Fallback to API-provided URL if construction failed
            if (! $downloadUrl) {
                $downloadUrl = $this->getFileDownloadUrlFromApi($fileId);
            }

            // Final fallback: Check if downloadUrl is provided in the API response
            if (! $downloadUrl) {
                $downloadUrl = $file['downloadUrl'] ?? null;
            }

            // Log the file structure for debugging
            Log::debug('CurseForge file data', [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'has_downloadUrl_in_file' => isset($file['downloadUrl']),
                'downloadUrl' => $downloadUrl,
                'fileName' => $fileName,
            ]);

            if (! $downloadUrl) {
                Log::warning('CurseForge download URL not available', [
                    'mod_id' => $modId,
                    'file_id' => $fileId,
                ]);

                return null;
            }

            return [
                'url' => $downloadUrl,
                'filename' => $fileName ?? "file-{$fileId}.jar",
            ];
        } catch (\Exception $e) {
            Log::error('CurseForge error getting file download info', [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
