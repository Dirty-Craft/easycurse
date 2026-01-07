<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ModService
{
    private CurseForgeService $curseForgeService;

    private ModrinthService $modrinthService;

    public function __construct(?CurseForgeService $curseForgeService = null, ?ModrinthService $modrinthService = null)
    {
        $this->curseForgeService = $curseForgeService ?? new CurseForgeService;
        $this->modrinthService = $modrinthService ?? new ModrinthService;
    }

    /**
     * Extract mod info from a URL (tries both CurseForge and Modrinth).
     *
     * @param  string  $url  The mod URL
     * @return array|null Array with 'source', 'slug', 'mod_id'/'project_id' keys, or null if URL is invalid
     */
    public function extractModInfoFromUrl(string $url): ?array
    {
        // Try CurseForge first
        $curseForgeInfo = $this->curseForgeService->extractModInfoFromUrl($url);
        if ($curseForgeInfo) {
            $curseForgeInfo['source'] = 'curseforge';
            if (isset($curseForgeInfo['mod_id'])) {
                $curseForgeInfo['id'] = $curseForgeInfo['mod_id'];
            }

            return $curseForgeInfo;
        }

        // Try Modrinth
        $modrinthInfo = $this->modrinthService->extractModInfoFromUrl($url);
        if ($modrinthInfo) {
            $modrinthInfo['source'] = 'modrinth';
            if (isset($modrinthInfo['project_id'])) {
                $modrinthInfo['id'] = $modrinthInfo['project_id'];
            }

            return $modrinthInfo;
        }

        return null;
    }

    /**
     * Search for mods by slug (searches both platforms).
     *
     * @param  string  $slug  The mod slug
     * @return array Array of mods with '_source' field indicating the platform
     */
    public function searchModBySlug(string $slug): array
    {
        $results = [];

        // Search CurseForge
        $curseForgeMod = $this->curseForgeService->searchModBySlug($slug);
        if ($curseForgeMod) {
            $curseForgeMod['_source'] = 'curseforge';
            // Normalize field names
            if (! isset($curseForgeMod['name']) && isset($curseForgeMod['title'])) {
                $curseForgeMod['name'] = $curseForgeMod['title'];
            }
            $results[] = $curseForgeMod;
        }

        // Search Modrinth
        $modrinthMod = $this->modrinthService->searchModBySlug($slug);
        if ($modrinthMod) {
            $modrinthMod['_source'] = 'modrinth';
            // Normalize field names
            if (! isset($modrinthMod['name']) && isset($modrinthMod['title'])) {
                $modrinthMod['name'] = $modrinthMod['title'];
            }
            if (! isset($modrinthMod['id']) && isset($modrinthMod['project_id'])) {
                $modrinthMod['id'] = $modrinthMod['project_id'];
            }
            $results[] = $modrinthMod;
        }

        return $results;
    }

    /**
     * Get mod details by ID or slug.
     *
     * @param  string|int  $modId  The mod ID or slug
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth'). If null, tries both.
     * @return array|null The mod data with '_source' field, or null if not found
     */
    public function getMod($modId, ?string $source = null): ?array
    {
        if ($source === 'curseforge' || $source === null) {
            if (is_numeric($modId)) {
                $mod = $this->curseForgeService->getMod((int) $modId);
                if ($mod) {
                    $mod['_source'] = 'curseforge';
                    // Normalize field names
                    if (! isset($mod['name']) && isset($mod['title'])) {
                        $mod['name'] = $mod['title'];
                    }

                    return $mod;
                }
            }
        }

        if ($source === 'modrinth' || $source === null) {
            $mod = $this->modrinthService->getProject((string) $modId);
            if ($mod) {
                $mod['_source'] = 'modrinth';
                // Normalize field names
                if (! isset($mod['name']) && isset($mod['title'])) {
                    $mod['name'] = $mod['title'];
                }
                if (! isset($mod['id']) && isset($mod['project_id'])) {
                    $mod['id'] = $mod['project_id'];
                }

                return $mod;
            }
        }

        return null;
    }

    /**
     * Get files/versions for a mod.
     *
     * @param  string|int  $modId  The mod ID or slug
     * @param  string|null  $gameVersion  Optional game version filter
     * @param  string|null  $software  Optional software/loader filter
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     * @return array Array of files/versions
     */
    public function getModFiles($modId, ?string $gameVersion = null, ?string $software = null, ?string $source = null): array
    {
        if ($source === 'curseforge' || ($source === null && is_numeric($modId))) {
            try {
                $files = $this->curseForgeService->getModFiles((int) $modId, $gameVersion, $software);
                if (! empty($files)) {
                    return $files;
                }
            } catch (\Exception $e) {
                Log::debug('CurseForge getModFiles failed', [
                    'mod_id' => $modId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($source === 'modrinth' || $source === null) {
            try {
                $versions = $this->modrinthService->getProjectVersions((string) $modId, $gameVersion, $software);
                if (! empty($versions)) {
                    return $versions;
                }
            } catch (\Exception $e) {
                Log::debug('Modrinth getProjectVersions failed', [
                    'mod_id' => $modId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    /**
     * Get a specific mod file/version by ID.
     *
     * @param  string|int  $modId  The mod ID
     * @param  string|int  $fileId  The file/version ID
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     * @return array|null The file/version data, or null if not found
     */
    public function getModFile($modId, $fileId, ?string $source = null): ?array
    {
        if ($source === 'curseforge' || ($source === null && is_numeric($modId) && is_numeric($fileId))) {
            $file = $this->curseForgeService->getModFile((int) $modId, (int) $fileId);
            if ($file) {
                return $file;
            }
        }

        if ($source === 'modrinth' || $source === null) {
            $version = $this->modrinthService->getVersion((string) $fileId);
            if ($version) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Search for mods with various filters (searches both platforms and merges results).
     *
     * @param  array  $filters  Search filters
     * @return array Array of mods with '_source' field indicating the platform
     */
    public function searchMods(array $filters = []): array
    {
        $results = [];

        // Search CurseForge
        try {
            $curseForgeFilters = $filters;
            // Map common filter names
            if (isset($filters['query'])) {
                $curseForgeFilters['searchFilter'] = $filters['query'];
                unset($curseForgeFilters['query']);
            }

            $curseForgeResults = $this->curseForgeService->searchMods($curseForgeFilters);
            foreach ($curseForgeResults as $mod) {
                $mod['_source'] = 'curseforge';
                // Normalize field names
                if (! isset($mod['name']) && isset($mod['title'])) {
                    $mod['name'] = $mod['title'];
                }
                $results[] = $mod;
            }
        } catch (\Exception $e) {
            Log::debug('CurseForge searchMods failed', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
        }

        // Search Modrinth
        try {
            $modrinthFilters = $filters;
            // Map common filter names
            if (isset($filters['searchFilter'])) {
                $modrinthFilters['query'] = $filters['searchFilter'];
                unset($modrinthFilters['searchFilter']);
            }

            $modrinthResults = $this->modrinthService->searchMods($modrinthFilters);
            foreach ($modrinthResults as $mod) {
                $mod['_source'] = 'modrinth';
                // Normalize field names
                if (! isset($mod['name']) && isset($mod['title'])) {
                    $mod['name'] = $mod['title'];
                }
                if (! isset($mod['id']) && isset($mod['project_id'])) {
                    $mod['id'] = $mod['project_id'];
                }
                $results[] = $mod;
            }
        } catch (\Exception $e) {
            Log::debug('Modrinth searchMods failed', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
        }

        // Remove duplicates by mod ID and source
        $uniqueResults = [];
        $seenKeys = [];
        foreach ($results as $result) {
            $source = $result['_source'] ?? 'unknown';
            $modId = $result['id'] ?? $result['project_id'] ?? null;
            $key = "{$source}:{$modId}";

            if ($modId && ! isset($seenKeys[$key])) {
                $uniqueResults[] = $result;
                $seenKeys[$key] = true;
            }
        }

        return $uniqueResults;
    }

    /**
     * Get the latest file/version for a mod matching the game version and software.
     *
     * @param  string|int  $modId  The mod ID or slug
     * @param  string  $gameVersion  The game version
     * @param  string  $software  The software/loader
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     * @return array|null The latest file/version data, or null if not found
     */
    public function getLatestModFile($modId, string $gameVersion, string $software, ?string $source = null): ?array
    {
        if ($source === 'curseforge' || ($source === null && is_numeric($modId))) {
            $file = $this->curseForgeService->getLatestModFile((int) $modId, $gameVersion, $software);
            if ($file) {
                return $file;
            }
        }

        if ($source === 'modrinth' || $source === null) {
            $version = $this->modrinthService->getLatestVersion((string) $modId, $gameVersion, $software);
            if ($version) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Get game versions for Minecraft (merges from both platforms).
     *
     * @return array Array of game versions
     */
    public function getGameVersions(): array
    {
        $curseForgeVersions = $this->curseForgeService->getGameVersions();
        $modrinthVersions = $this->modrinthService->getGameVersions();

        return $this->mergeGameVersions($curseForgeVersions, $modrinthVersions);
    }

    /**
     * Get available mod loaders (merges from both platforms).
     *
     * @return array Array of mod loaders
     */
    public function getModLoaders(): array
    {
        $curseForgeLoaders = $this->curseForgeService->getModLoaders();
        $modrinthLoaders = $this->modrinthService->getModLoaders();

        return $this->mergeModLoaders($curseForgeLoaders, $modrinthLoaders);
    }

    /**
     * Get dependencies for a file/version.
     *
     * @param  array  $fileData  The file/version data
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     * @return array Array with 'required', 'optional', 'embedded' keys
     */
    public function getFileDependencies(array $fileData, ?string $source = null): array
    {
        // Try to detect source from data structure
        if ($source === null) {
            // CurseForge files have 'dependencies' with 'modId' and 'relationType'
            // Modrinth versions have 'dependencies' with 'project_id' and 'dependency_type'
            if (isset($fileData['dependencies']) && is_array($fileData['dependencies'])) {
                $firstDep = $fileData['dependencies'][0] ?? [];
                if (isset($firstDep['modId'])) {
                    $source = 'curseforge';
                } elseif (isset($firstDep['project_id'])) {
                    $source = 'modrinth';
                }
            }
        }

        if ($source === 'curseforge') {
            return $this->curseForgeService->getFileDependencies($fileData);
        } elseif ($source === 'modrinth') {
            return $this->modrinthService->getVersionDependencies($fileData);
        }

        return [
            'required' => [],
            'optional' => [],
            'embedded' => [],
        ];
    }

    /**
     * Get download information for a mod file/version.
     *
     * @param  string|int  $modId  The mod ID
     * @param  string|int  $fileId  The file/version ID
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     * @return array|null Array with 'url' and 'filename' keys, or null if unavailable
     */
    public function getFileDownloadInfo($modId, $fileId, ?string $source = null): ?array
    {
        if ($source === 'curseforge' || ($source === null && is_numeric($modId) && is_numeric($fileId))) {
            $info = $this->curseForgeService->getFileDownloadInfo((int) $modId, (int) $fileId);
            if ($info) {
                return $info;
            }
        }

        if ($source === 'modrinth' || $source === null) {
            $info = $this->modrinthService->getVersionDownloadInfoById((string) $modId, (string) $fileId);
            if ($info) {
                return $info;
            }
        }

        return null;
    }

    /**
     * Invalidate cache for a specific mod.
     *
     * @param  string|int  $modId  The mod ID
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     */
    public function invalidateModCache($modId, ?string $source = null): void
    {
        if ($source === 'curseforge' || ($source === null && is_numeric($modId))) {
            $this->curseForgeService->invalidateModCache((int) $modId);
        }

        if ($source === 'modrinth' || $source === null) {
            $this->modrinthService->invalidateModCache((string) $modId);
        }
    }

    /**
     * Invalidate cache for a specific mod file/version.
     *
     * @param  string|int  $modId  The mod ID
     * @param  string|int  $fileId  The file/version ID
     * @param  string|null  $source  The source platform ('curseforge' or 'modrinth')
     */
    public function invalidateModFileCache($modId, $fileId, ?string $source = null): void
    {
        if ($source === 'curseforge' || ($source === null && is_numeric($modId) && is_numeric($fileId))) {
            $this->curseForgeService->invalidateModFileCache((int) $modId, (int) $fileId);
        }

        if ($source === 'modrinth' || $source === null) {
            $this->modrinthService->invalidateModVersionCache((string) $modId, (string) $fileId);
        }
    }

    /**
     * Merge game versions from both services, removing duplicates.
     *
     * @param  array  $curseforgeVersions  CurseForge game versions
     * @param  array  $modrinthVersions  Modrinth game versions
     * @return array Merged and sorted game versions
     */
    private function mergeGameVersions(array $curseforgeVersions, array $modrinthVersions): array
    {
        $merged = [];
        $seen = [];

        // Add CurseForge versions
        foreach ($curseforgeVersions as $version) {
            $name = $version['name'] ?? '';
            if (! empty($name) && ! isset($seen[$name])) {
                $merged[] = $version;
                $seen[$name] = true;
            }
        }

        // Add Modrinth versions (skip duplicates)
        foreach ($modrinthVersions as $version) {
            $name = $version['name'] ?? '';
            if (! empty($name) && ! isset($seen[$name])) {
                $merged[] = $version;
                $seen[$name] = true;
            }
        }

        // Sort by version (newest first)
        usort($merged, function ($a, $b) {
            $aVersion = preg_replace('/[^0-9.]/', '', $a['name'] ?? '');
            $bVersion = preg_replace('/[^0-9.]/', '', $b['name'] ?? '');

            return version_compare($bVersion, $aVersion);
        });

        return array_values($merged);
    }

    /**
     * Merge mod loaders from both services, removing duplicates.
     *
     * @param  array  $curseforgeLoaders  CurseForge mod loaders
     * @param  array  $modrinthLoaders  Modrinth mod loaders
     * @return array Merged mod loaders
     */
    private function mergeModLoaders(array $curseforgeLoaders, array $modrinthLoaders): array
    {
        $merged = [];
        $seen = [];

        // Add CurseForge loaders
        foreach ($curseforgeLoaders as $loader) {
            $slug = $loader['slug'] ?? '';
            if (! empty($slug) && ! isset($seen[$slug])) {
                $merged[] = $loader;
                $seen[$slug] = true;
            }
        }

        // Add Modrinth loaders (skip duplicates)
        foreach ($modrinthLoaders as $loader) {
            $slug = $loader['slug'] ?? '';
            if (! empty($slug) && ! isset($seen[$slug])) {
                $merged[] = $loader;
                $seen[$slug] = true;
            }
        }

        return array_values($merged);
    }
}
