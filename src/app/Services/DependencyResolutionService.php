<?php

namespace App\Services;

use App\Models\ModPack;

class DependencyResolutionService
{
    private ModService $modService;

    public function __construct(?ModService $modService = null)
    {
        $this->modService = $modService ?? new ModService;
    }

    /**
     * Get full dependency tree for a mod file/version.
     * This recursively fetches all dependencies and their dependencies.
     *
     * @param  string|int  $modId  The mod ID
     * @param  string|int  $fileId  The file/version ID
     * @param  string  $source  The source platform ('curseforge' or 'modrinth')
     * @param  string  $gameVersion  The Minecraft version
     * @param  string  $software  The mod loader (forge, fabric, etc.)
     * @param  array  $visited  Internal: track visited mods to prevent cycles
     * @return array Dependency tree with 'required', 'optional', 'embedded', 'incompatible' and 'tree' keys
     */
    public function getDependencyTree(
        $modId,
        $fileId,
        string $source,
        string $gameVersion,
        string $software,
        array $visited = []
    ): array {
        $visitedKey = "{$source}:{$modId}";
        if (isset($visited[$visitedKey])) {
            return [];
        }
        $visited[$visitedKey] = true;

        // Get the file/version data
        $fileData = $this->modService->getModFile($modId, $fileId, $source);
        if (! $fileData) {
            return [];
        }

        // Get dependencies for this file/version
        $dependencies = $this->modService->getFileDependencies($fileData, $source);

        // Build dependency tree recursively
        $tree = [
            'mod_id' => $modId,
            'file_id' => $fileId,
            'source' => $source,
            'dependencies' => [
                'required' => [],
                'optional' => [],
                'embedded' => [],
                'incompatible' => [],
            ],
        ];

        // Recursively fetch dependencies for required dependencies
        foreach ($dependencies['required'] as $depModId) {
            // Try to find a compatible version for this dependency
            $depFiles = $this->modService->getModFiles(
                $depModId,
                $gameVersion,
                $software,
                $source
            );

            if (! empty($depFiles)) {
                // Use the latest compatible version
                $depFile = $depFiles[0];
                $depFileId = $source === 'curseforge' ? $depFile['id'] : $depFile['id'];

                $depTree = $this->getDependencyTree(
                    $depModId,
                    $depFileId,
                    $source,
                    $gameVersion,
                    $software,
                    $visited
                );

                $tree['dependencies']['required'][] = [
                    'mod_id' => $depModId,
                    'file_id' => $depFileId,
                    'source' => $source,
                    'tree' => $depTree,
                ];
            }
        }

        // For optional and incompatible, just store the IDs (don't recurse)
        foreach ($dependencies['optional'] as $depModId) {
            $tree['dependencies']['optional'][] = [
                'mod_id' => $depModId,
                'source' => $source,
            ];
        }

        foreach ($dependencies['incompatible'] as $depModId) {
            $tree['dependencies']['incompatible'][] = [
                'mod_id' => $depModId,
                'source' => $source,
            ];
        }

        return $tree;
    }

    /**
     * Get all required dependencies (flattened list) for a mod file/version.
     *
     * @param  string|int  $modId  The mod ID
     * @param  string|int  $fileId  The file/version ID
     * @param  string  $source  The source platform
     * @param  string  $gameVersion  The Minecraft version
     * @param  string  $software  The mod loader
     * @return array Array of ['mod_id', 'file_id', 'source'] entries
     */
    public function getRequiredDependencies(
        $modId,
        $fileId,
        string $source,
        string $gameVersion,
        string $software
    ): array {
        $tree = $this->getDependencyTree($modId, $fileId, $source, $gameVersion, $software);
        $dependencies = [];

        $this->flattenDependencyTree($tree, $dependencies);

        return $dependencies;
    }

    /**
     * Flatten a dependency tree into a list of dependencies.
     *
     * @param  array  $tree  The dependency tree
     * @param  array  &$result  Reference to result array
     */
    private function flattenDependencyTree(array $tree, array &$result): void
    {
        foreach ($tree['dependencies']['required'] ?? [] as $dep) {
            $key = "{$dep['source']}:{$dep['mod_id']}";
            if (! isset($result[$key])) {
                $result[$key] = [
                    'mod_id' => $dep['mod_id'],
                    'file_id' => $dep['file_id'] ?? null,
                    'source' => $dep['source'],
                ];
            }

            // Recursively process sub-dependencies
            if (isset($dep['tree']) && ! empty($dep['tree'])) {
                $this->flattenDependencyTree($dep['tree'], $result);
            }
        }
    }

    /**
     * Check for conflicts between a mod and existing mods in a mod pack.
     *
     * @param  string|int  $modId  The mod ID being added
     * @param  string|int  $fileId  The file/version ID
     * @param  string  $source  The source platform
     * @param  ModPack  $modPack  The mod pack
     * @return array Array of conflicting mod IDs with their details
     */
    public function checkConflicts(
        $modId,
        $fileId,
        string $source,
        ModPack $modPack
    ): array {
        $conflicts = [];

        // Get dependencies for the mod being added
        $fileData = $this->modService->getModFile($modId, $fileId, $source);
        if (! $fileData) {
            return $conflicts;
        }

        $dependencies = $this->modService->getFileDependencies($fileData, $source);

        // Check for incompatible dependencies
        foreach ($dependencies['incompatible'] ?? [] as $incompatibleModId) {
            // Check if this incompatible mod exists in the mod pack
            foreach ($modPack->items as $item) {
                if ($source === 'curseforge' && $item->curseforge_mod_id == $incompatibleModId) {
                    $conflicts[] = [
                        'type' => 'incompatible',
                        'mod_id' => $incompatibleModId,
                        'existing_mod_name' => $item->mod_name,
                        'source' => $source,
                    ];
                } elseif ($source === 'modrinth' && $item->modrinth_project_id == $incompatibleModId) {
                    $conflicts[] = [
                        'type' => 'incompatible',
                        'mod_id' => $incompatibleModId,
                        'existing_mod_name' => $item->mod_name,
                        'source' => $source,
                    ];
                }
            }
        }

        // Also check reverse: if any existing mods are incompatible with the new mod
        foreach ($modPack->items as $item) {
            $itemSource = $item->source;
            $itemModId = $itemSource === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
            $itemFileId = $itemSource === 'curseforge' ? $item->curseforge_file_id : $item->modrinth_version_id;

            if (! $itemModId || ! $itemFileId) {
                continue;
            }

            $itemFileData = $this->modService->getModFile($itemModId, $itemFileId, $itemSource);
            if (! $itemFileData) {
                continue;
            }

            $itemDependencies = $this->modService->getFileDependencies($itemFileData, $itemSource);

            // Check if this item is incompatible with the new mod
            foreach ($itemDependencies['incompatible'] ?? [] as $incompatibleModId) {
                if (($source === 'curseforge' && $incompatibleModId == $modId) ||
                    ($source === 'modrinth' && $incompatibleModId == $modId)) {
                    $conflicts[] = [
                        'type' => 'incompatible',
                        'mod_id' => $itemModId,
                        'existing_mod_name' => $item->mod_name,
                        'source' => $itemSource,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Validate all dependencies in a mod pack and return missing dependencies.
     *
     * @param  ModPack  $modPack  The mod pack
     * @return array Array of missing dependencies
     */
    public function validateModPackDependencies(ModPack $modPack): array
    {
        $missingDependencies = [];
        $existingModIds = [];

        // Build list of existing mod IDs
        foreach ($modPack->items as $item) {
            $itemSource = $item->source;
            if ($itemSource === 'curseforge' && $item->curseforge_mod_id) {
                $existingModIds["curseforge:{$item->curseforge_mod_id}"] = true;
            } elseif ($itemSource === 'modrinth' && $item->modrinth_project_id) {
                $existingModIds["modrinth:{$item->modrinth_project_id}"] = true;
            }
        }

        // Check each mod's dependencies
        foreach ($modPack->items as $item) {
            $itemSource = $item->source;
            $itemModId = $itemSource === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
            $itemFileId = $itemSource === 'curseforge' ? $item->curseforge_file_id : $item->modrinth_version_id;

            if (! $itemModId || ! $itemFileId) {
                continue;
            }

            $fileData = $this->modService->getModFile($itemModId, $itemFileId, $itemSource);
            if (! $fileData) {
                continue;
            }

            $dependencies = $this->modService->getFileDependencies($fileData, $itemSource);

            // Check required dependencies
            foreach ($dependencies['required'] ?? [] as $requiredModId) {
                $key = "{$itemSource}:{$requiredModId}";
                if (! isset($existingModIds[$key])) {
                    $missingDependencies[] = [
                        'required_by_mod_id' => $itemModId,
                        'required_by_mod_name' => $item->mod_name,
                        'missing_mod_id' => $requiredModId,
                        'source' => $itemSource,
                    ];
                }
            }
        }

        return $missingDependencies;
    }
}
