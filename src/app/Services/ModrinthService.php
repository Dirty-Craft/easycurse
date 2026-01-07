<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModrinthService
{
    private string $baseUrl;

    private string $userAgent;

    /**
     * Cache TTL constants (in seconds).
     */
    private const CACHE_TTL_GAME_VERSIONS = 24 * 60 * 60; // 24 hours

    private const CACHE_TTL_MOD_DETAILS = 12 * 60 * 60; // 12 hours

    private const CACHE_TTL_MOD_VERSIONS_LIST = 60 * 60; // 1 hour

    private const CACHE_TTL_MOD_VERSION = 2 * 60 * 60; // 2 hours

    private const CACHE_TTL_SEARCH_RESULTS = 30 * 60; // 30 minutes

    private const CACHE_TTL_SLUG_SEARCH = 6 * 60 * 60; // 6 hours

    private const CACHE_TTL_DOWNLOAD_URL = 6 * 60 * 60; // 6 hours

    /**
     * Cache key prefix.
     */
    private const CACHE_PREFIX = 'modrinth:';

    public function __construct()
    {
        $this->baseUrl = config('services.modrinth.base_url', 'https://api.modrinth.com/v2/');
        $this->userAgent = config('services.modrinth.user_agent', 'EasyCurse/1.0.0 (contact@example.com)');
    }

    /**
     * Get the HTTP client with required headers.
     */
    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Generate a cache key with the service prefix.
     */
    private function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX.$key;
    }

    /**
     * Invalidate cache for a specific mod (useful when mod data might have changed).
     */
    public function invalidateModCache(string $projectId): void
    {
        $keys = [
            $this->cacheKey("project:{$projectId}"),
            $this->cacheKey("project:{$projectId}:versions"),
            $this->cacheKey("project:{$projectId}:versions:*"), // Pattern for versioned file lists
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Also clear pattern-based keys
        Cache::forget($this->cacheKey("project:{$projectId}:versions:all"));
    }

    /**
     * Invalidate cache for a specific mod version.
     */
    public function invalidateModVersionCache(string $projectId, string $versionId): void
    {
        Cache::forget($this->cacheKey("project:{$projectId}:version:{$versionId}"));
        Cache::forget($this->cacheKey("version:{$versionId}"));
        Cache::forget($this->cacheKey("version:{$versionId}:download"));
    }

    /**
     * Extract project ID or slug from a Modrinth URL.
     *
     * @param  string  $url  The Modrinth URL
     * @return array|null Array with 'slug' or 'project_id' key, or null if URL is invalid
     */
    public function extractModInfoFromUrl(string $url): ?array
    {
        // Common Modrinth URL patterns:
        // https://modrinth.com/mod/{slug}
        // https://www.modrinth.com/mod/{slug}
        // https://modrinth.com/project/{slug}
        // https://www.modrinth.com/project/{slug}
        // URLs may have query params (?anything) or fragments (#anything)

        // Pattern to match: modrinth.com/mod/{slug} or modrinth.com/project/{slug}
        $pattern = '/modrinth\.com\/(?:mod|project)\/([a-z0-9\-_]+)/i';
        if (preg_match($pattern, $url, $matches)) {
            $slug = trim($matches[1], '/');
            // Remove any trailing path segments, query params, or fragments
            $slug = preg_replace('/[\/?#].*$/', '', $slug);
            $slug = trim($slug);

            if (! empty($slug)) {
                Log::debug('Extracted slug from Modrinth URL', [
                    'url' => $url,
                    'slug' => $slug,
                    'raw_match' => $matches[1] ?? null,
                ]);

                return ['slug' => $slug];
            }
        }

        Log::debug('Failed to extract mod info from Modrinth URL', [
            'url' => $url,
        ]);

        return null;
    }

    /**
     * Search for mods by slug.
     * Results are cached for 6 hours since slug-to-mod mappings are stable.
     */
    public function searchModBySlug(string $slug): ?array
    {
        $cacheKey = $this->cacheKey("search:slug:{$slug}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SLUG_SEARCH, function () use ($slug) {
            try {
                // Try to get project directly by slug first
                $project = $this->getProject($slug);
                if ($project) {
                    return $project;
                }

                // If direct lookup fails, try search
                $response = $this->client()->get($this->baseUrl.'search', [
                    'query' => $slug,
                    'facets' => json_encode([['project_type:mod']]),
                    'limit' => 1,
                ]);

                $response->throw();

                $data = $response->json('hits', []);

                return ! empty($data) ? $data[0] : null;
            } catch (RequestException $e) {
                Log::error('Modrinth API error searching mod by slug', [
                    'slug' => $slug,
                    'error' => $e->getMessage(),
                ]);

                // Don't cache errors - return null and let it retry next time
                return null;
            }
        });
    }

    /**
     * Get project details by ID or slug.
     * Results are cached for 12 hours since project details don't change frequently.
     */
    public function getProject(string $projectIdOrSlug): ?array
    {
        $cacheKey = $this->cacheKey("project:{$projectIdOrSlug}");

        return Cache::remember($cacheKey, self::CACHE_TTL_MOD_DETAILS, function () use ($projectIdOrSlug) {
            try {
                $response = $this->client()->get($this->baseUrl.'project/'.$projectIdOrSlug);

                $response->throw();

                return $response->json();
            } catch (RequestException $e) {
                Log::error('Modrinth API error getting project', [
                    'project_id_or_slug' => $projectIdOrSlug,
                    'error' => $e->getMessage(),
                ]);

                // Don't cache errors - return null and let it retry next time
                return null;
            }
        });
    }

    /**
     * Get versions for a project.
     * Results are cached for 1 hour since version lists can change more frequently.
     */
    public function getProjectVersions(string $projectIdOrSlug, ?string $gameVersion = null, ?string $loader = null): array
    {
        // Build cache key based on parameters
        $cacheKeyParts = ["project:{$projectIdOrSlug}:versions"];
        if ($gameVersion) {
            $cacheKeyParts[] = 'v:'.md5($gameVersion);
        }
        if ($loader) {
            $cacheKeyParts[] = 'l:'.strtolower($loader);
        }
        $cacheKey = $this->cacheKey(implode(':', $cacheKeyParts));

        return Cache::remember($cacheKey, self::CACHE_TTL_MOD_VERSIONS_LIST, function () use ($projectIdOrSlug, $gameVersion, $loader) {
            try {
                $queryParams = [];

                if ($gameVersion) {
                    $queryParams['game_versions'] = json_encode([$gameVersion]);
                }

                if ($loader) {
                    // Map loader string to Modrinth loader format
                    $modrinthLoader = match (strtolower($loader)) {
                        'forge' => 'forge',
                        'fabric' => 'fabric',
                        'quilt' => 'quilt',
                        'neoforge' => 'neoforge',
                        default => null,
                    };

                    if ($modrinthLoader) {
                        $queryParams['loaders'] = json_encode([$modrinthLoader]);
                    }
                }

                $response = $this->client()->get($this->baseUrl.'project/'.$projectIdOrSlug.'/version', $queryParams);

                $response->throw();

                $versions = $response->json();

                // If a specific game version was requested, filter results to ensure strict matching
                if ($gameVersion && ! empty($versions)) {
                    $versions = $this->filterVersionsByExactVersion($versions, $gameVersion);
                }

                return is_array($versions) ? $versions : [];
            } catch (RequestException $e) {
                Log::error('Modrinth API error getting project versions', [
                    'project_id_or_slug' => $projectIdOrSlug,
                    'game_version' => $gameVersion,
                    'loader' => $loader,
                    'error' => $e->getMessage(),
                ]);

                // Don't cache errors - return empty array and let it retry next time
                return [];
            }
        });
    }

    /**
     * Filter versions to only include those that support the exact game version.
     * This ensures strict version matching (e.g., "1.20" != "1.20.1").
     *
     * @param  array  $versions  Array of version data from Modrinth API
     * @param  string  $requestedVersion  The exact Minecraft version to match
     * @return array Filtered array of versions
     */
    private function filterVersionsByExactVersion(array $versions, string $requestedVersion): array
    {
        // Normalize the requested version for comparison
        $normalizedRequested = $this->normalizeVersion($requestedVersion);

        $filtered = [];

        foreach ($versions as $version) {
            $gameVersions = $version['game_versions'] ?? [];

            // If it's not an array, skip this version
            if (! is_array($gameVersions)) {
                continue;
            }

            // Check if any of the version's game versions exactly match the requested version
            $hasExactMatch = false;
            foreach ($gameVersions as $fileVersion) {
                // Skip if not a string
                if (! is_string($fileVersion)) {
                    continue;
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
                $filtered[] = $version;
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
     * Get a specific version by ID.
     * Results are cached for 2 hours since individual version data is relatively stable.
     */
    public function getVersion(string $versionId): ?array
    {
        $cacheKey = $this->cacheKey("version:{$versionId}");

        return Cache::remember($cacheKey, self::CACHE_TTL_MOD_VERSION, function () use ($versionId) {
            try {
                $response = $this->client()->get($this->baseUrl.'version/'.$versionId);

                $response->throw();

                return $response->json();
            } catch (RequestException $e) {
                Log::error('Modrinth API error getting version', [
                    'version_id' => $versionId,
                    'error' => $e->getMessage(),
                ]);

                // Don't cache errors - return null and let it retry next time
                return null;
            }
        });
    }

    /**
     * Search for mods with various filters.
     * Results are cached for 30 minutes since search results can change.
     */
    public function searchMods(array $filters = []): array
    {
        // Build cache key from filters - sort to ensure consistent keys
        $filtersKey = md5(json_encode($filters, JSON_THROW_ON_ERROR));
        $cacheKey = $this->cacheKey("search:mods:{$filtersKey}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SEARCH_RESULTS, function () use ($filters) {
            try {
                $queryParams = array_merge([
                    'facets' => json_encode([['project_type:mod']]),
                    'limit' => 20,
                ], $filters);

                $response = $this->client()->get($this->baseUrl.'search', $queryParams);

                $response->throw();

                return $response->json('hits', []);
            } catch (RequestException $e) {
                Log::error('Modrinth API error searching mods', [
                    'filters' => $filters,
                    'error' => $e->getMessage(),
                ]);

                // Don't cache errors - return empty array and let it retry next time
                return [];
            }
        });
    }

    /**
     * Get the latest version for a project matching the game version and loader.
     */
    public function getLatestVersion(string $projectIdOrSlug, string $gameVersion, string $loader): ?array
    {
        $versions = $this->getProjectVersions($projectIdOrSlug, $gameVersion, $loader);

        if (empty($versions)) {
            return null;
        }

        // Versions are typically returned in descending order (newest first)
        // Return the first version (latest)
        return $versions[0];
    }

    /**
     * Get game versions for Minecraft.
     * Results are cached for 24 hours to avoid excessive API calls.
     */
    public function getGameVersions(): array
    {
        $cacheKey = $this->cacheKey('game_versions');

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        // Cache is empty or doesn't exist, fetch from API
        try {
            $url = $this->baseUrl.'tag/game_version';

            Log::debug('Fetching game versions from Modrinth API', [
                'url' => $url,
            ]);

            $response = $this->client()->get($url);

            $statusCode = $response->status();
            $responseBody = $response->json();

            Log::debug('Modrinth API response', [
                'status' => $statusCode,
                'has_data' => is_array($responseBody),
                'data_count' => is_array($responseBody) ? count($responseBody) : 0,
            ]);

            // Check for HTTP errors first
            if (! $response->successful()) {
                Log::error('Modrinth API request failed', [
                    'status' => $statusCode,
                    'url' => $url,
                    'response' => $responseBody,
                    'body' => $response->body(),
                ]);

                return [];
            }

            $versions = is_array($responseBody) ? $responseBody : [];

            if (empty($versions)) {
                Log::warning('Modrinth API returned empty game versions', [
                    'response' => $responseBody,
                    'status' => $statusCode,
                ]);

                return [];
            }

            // Filter to only include major versions (not snapshots, betas, etc.)
            // and format them consistently
            $formattedVersions = [];
            foreach ($versions as $version) {
                // Skip if not a release version
                if (! isset($version['version_type']) || $version['version_type'] !== 'release') {
                    continue;
                }

                $versionString = $version['version'] ?? $version['name'] ?? null;
                if (! $versionString) {
                    continue;
                }

                $formattedVersions[] = [
                    'id' => $version['id'] ?? null,
                    'name' => $versionString,
                    'slug' => $version['slug'] ?? str_replace('.', '-', $versionString),
                    'type' => $version['version_type'] ?? 'release',
                ];
            }

            // Sort versions - newest first using version_compare
            usort($formattedVersions, function ($a, $b) {
                // Remove any non-version characters for comparison
                $aVersion = preg_replace('/[^0-9.]/', '', $a['name']);
                $bVersion = preg_replace('/[^0-9.]/', '', $b['name']);

                return version_compare($bVersion, $aVersion);
            });

            Log::info('Successfully loaded game versions', [
                'count' => count($formattedVersions),
                'first' => $formattedVersions[0]['name'] ?? null,
                'last' => end($formattedVersions)['name'] ?? null,
            ]);

            $result = array_values($formattedVersions);

            // Only cache if we have valid data
            if (! empty($result)) {
                Cache::put($cacheKey, $result, self::CACHE_TTL_GAME_VERSIONS);
            }

            return $result;
        } catch (RequestException $e) {
            $errorDetails = [
                'url' => $this->baseUrl.'tag/game_version',
                'status' => $e->response?->status(),
                'error' => $e->getMessage(),
                'response_body' => $e->response?->body(),
                'response_json' => $e->response?->json(),
            ];

            Log::error('Modrinth API error getting game versions', $errorDetails);

            // Don't cache errors - return empty array and let it retry next time
            return [];
        } catch (\Exception $e) {
            Log::error('Unexpected error getting game versions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't cache errors - return empty array and let it retry next time
            return [];
        }
    }

    /**
     * Get available mod loaders.
     * Returns common mod loaders with their Modrinth identifiers.
     */
    public function getModLoaders(): array
    {
        // Modrinth loader identifiers
        return [
            ['id' => 'forge', 'name' => 'Forge', 'slug' => 'forge'],
            ['id' => 'fabric', 'name' => 'Fabric', 'slug' => 'fabric'],
            ['id' => 'quilt', 'name' => 'Quilt', 'slug' => 'quilt'],
            ['id' => 'neoforge', 'name' => 'NeoForge', 'slug' => 'neoforge'],
        ];
    }

    /**
     * Get version dependencies.
     *
     * @param  array  $versionData  The version data from getVersion or getProjectVersions
     * @return array Array of dependency project IDs with their relation types
     */
    public function getVersionDependencies(array $versionData): array
    {
        $dependencies = $versionData['dependencies'] ?? [];

        $result = [
            'required' => [],
            'optional' => [],
            'embedded' => [],
        ];

        foreach ($dependencies as $dependency) {
            $projectId = $dependency['project_id'] ?? null;
            $dependencyType = $dependency['dependency_type'] ?? null;

            if (! $projectId) {
                continue;
            }

            // Dependency types: 'required', 'optional', 'incompatible', 'embedded'
            match ($dependencyType) {
                'embedded' => $result['embedded'][] = $projectId,
                'optional' => $result['optional'][] = $projectId,
                'required' => $result['required'][] = $projectId,
                default => null,
            };
        }

        return $result;
    }

    /**
     * Get the download URL for a version file.
     *
     * @param  array  $versionData  The version data from getVersion or getProjectVersions
     * @return array|null Array with 'url' and 'filename' keys, or null if unavailable
     */
    public function getVersionDownloadInfo(array $versionData): ?array
    {
        $files = $versionData['files'] ?? [];

        if (empty($files)) {
            Log::warning('Modrinth version has no files', [
                'version_id' => $versionData['id'] ?? null,
            ]);

            return null;
        }

        // Get the primary file (usually the first one, or one marked as primary)
        $primaryFile = null;
        foreach ($files as $file) {
            if (isset($file['primary']) && $file['primary'] === true) {
                $primaryFile = $file;
                break;
            }
        }

        // If no primary file found, use the first one
        if (! $primaryFile) {
            $primaryFile = $files[0];
        }

        $downloadUrl = $primaryFile['url'] ?? null;
        $filename = $primaryFile['filename'] ?? null;

        if (! $downloadUrl) {
            Log::warning('Modrinth version file has no download URL', [
                'version_id' => $versionData['id'] ?? null,
                'file' => $primaryFile,
            ]);

            return null;
        }

        return [
            'url' => $downloadUrl,
            'filename' => $filename ?? 'mod.jar',
        ];
    }

    /**
     * Get download information for a version (including URL and filename).
     * Results are cached for 6 hours since download URLs are stable.
     *
     * @param  string  $projectIdOrSlug  The Modrinth project ID or slug
     * @param  string  $versionId  The Modrinth version ID
     * @return array|null Array with 'url' and 'filename' keys, or null if unavailable
     */
    public function getVersionDownloadInfoById(string $projectIdOrSlug, string $versionId): ?array
    {
        $cacheKey = $this->cacheKey("version:{$versionId}:download");

        return Cache::remember($cacheKey, self::CACHE_TTL_DOWNLOAD_URL, function () use ($versionId) {
            try {
                $version = $this->getVersion($versionId);

                if (! $version) {
                    Log::warning('Modrinth version not found', [
                        'version_id' => $versionId,
                    ]);

                    // Don't cache null results - return null and let it retry next time
                    return null;
                }

                return $this->getVersionDownloadInfo($version);
            } catch (\Exception $e) {
                Log::error('Modrinth error getting version download info', [
                    'project_id_or_slug' => $projectIdOrSlug,
                    'version_id' => $versionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Don't cache errors - return null and let it retry next time
                return null;
            }
        });
    }
}
