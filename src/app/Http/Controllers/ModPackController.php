<?php

namespace App\Http\Controllers;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Services\DependencyResolutionService;
use App\Services\ModPackExportService;
use App\Services\ModService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ModPackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $modPacks = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->latest()
            ->get();

        $modService = new ModService;
        $gameVersions = $modService->getGameVersions();
        $modLoaders = $modService->getModLoaders();

        return Inertia::render('ModPacks/Index', [
            'modPacks' => $modPacks,
            'gameVersions' => $gameVersions,
            'modLoaders' => $modLoaders,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'minecraft_version' => ['required', 'string', 'max:255'],
            'software' => ['required', 'string', 'in:forge,fabric,quilt,neoforge'],
            'description' => ['nullable', 'string'],
        ]);

        $modPack = ModPack::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $modService = new ModService;
        $gameVersions = $modService->getGameVersions();
        $modLoaders = $modService->getModLoaders();

        return Inertia::render('ModPacks/Show', [
            'modPack' => $modPack,
            'gameVersions' => $gameVersions,
            'modLoaders' => $modLoaders,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $modPack->update($validated);

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);
        $modPack->delete();

        return redirect()->route('mod-packs.index');
    }

    /**
     * Search for mods using CurseForge and Modrinth APIs.
     */
    public function searchMods(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $modService = new ModService;
        $query = trim($validated['query']);

        $results = [];

        // Check if the query looks like a URL
        $isUrl = (str_starts_with($query, 'http://') || str_starts_with($query, 'https://'));

        // Handle URL search
        if ($isUrl) {
            $modInfo = $modService->extractModInfoFromUrl($query);
            if ($modInfo) {
                $source = $modInfo['source'] ?? null;
                $modId = $modInfo['id'] ?? $modInfo['mod_id'] ?? $modInfo['project_id'] ?? null;
                $slug = $modInfo['slug'] ?? null;

                if ($slug) {
                    // Search by slug
                    $mods = $modService->searchModBySlug($slug);
                    $results = array_merge($results, $mods);
                } elseif ($modId) {
                    // Get mod by ID
                    $mod = $modService->getMod($modId, $source);
                    if ($mod) {
                        $results[] = $mod;
                    }
                }
            }
        }

        // If URL search didn't return results, try slug search on both platforms
        if (empty($results) && preg_match('/^[a-z0-9-]+$/', $query)) {
            $mods = $modService->searchModBySlug($query);
            $results = array_merge($results, $mods);
        }

        // Also try general search on both platforms
        if (empty($results) || ! $isUrl) {
            $searchResults = $modService->searchMods([
                'query' => $query,
            ]);
            $results = array_merge($results, $searchResults);
        }

        // Remove duplicates by mod ID and source
        // ModService already normalizes field names, but we still need to deduplicate
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

        return response()->json([
            'data' => array_slice($uniqueResults, 0, 20), // Limit to 20 results
        ]);
    }

    /**
     * Get mod files for a specific mod.
     */
    public function getModFiles(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'mod_id' => ['required'],
            'source' => ['nullable', 'string', 'in:curseforge,modrinth'],
        ]);

        // Default to curseforge if source is not provided (for backward compatibility)
        if (empty($validated['source'])) {
            $validated['source'] = 'curseforge';
        }

        $modService = new ModService;
        $files = $modService->getModFiles(
            $validated['mod_id'],
            $modPack->minecraft_version,
            $modPack->software,
            $validated['source']
        );

        return response()->json([
            'data' => $files,
        ]);
    }

    /**
     * Get dependencies for a specific mod file/version.
     */
    public function getModDependencies(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $validated = $request->validate([
            'mod_id' => ['required'],
            'file_id' => ['required'],
            'source' => ['required', 'string', 'in:curseforge,modrinth'],
        ]);

        $dependencyService = new DependencyResolutionService;
        $tree = $dependencyService->getDependencyTree(
            $validated['mod_id'],
            $validated['file_id'],
            $validated['source'],
            $modPack->minecraft_version,
            $modPack->software
        );

        // Also check for conflicts
        $conflicts = $dependencyService->checkConflicts(
            $validated['mod_id'],
            $validated['file_id'],
            $validated['source'],
            $modPack
        );

        return response()->json([
            'tree' => $tree,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Add required dependencies for a mod to the mod pack.
     *
     * @param  ModPack  $modPack  The mod pack
     * @param  string|int  $modId  The mod ID
     * @param  string|int  $fileId  The file/version ID
     * @param  string  $source  The source platform
     * @param  array  $visited  Internal: track visited mods to prevent cycles
     * @return int Number of dependencies added
     */
    private function addRequiredDependencies(
        ModPack $modPack,
        $modId,
        $fileId,
        string $source,
        array &$visited = []
    ): int {
        $visitedKey = "{$source}:{$modId}";
        if (isset($visited[$visitedKey])) {
            return 0; // Already processing this mod, prevent cycles
        }
        $visited[$visitedKey] = true;
        $dependencyService = new DependencyResolutionService;
        $modService = new ModService;

        // Get all required dependencies (flattened list)
        $requiredDependencies = $dependencyService->getRequiredDependencies(
            $modId,
            $fileId,
            $source,
            $modPack->minecraft_version,
            $modPack->software
        );

        $addedCount = 0;
        $maxSortOrder = (int) (ModPackItem::where('mod_pack_id', $modPack->id)->max('sort_order') ?? 0);

        foreach ($requiredDependencies as $dep) {
            $depModId = $dep['mod_id'];
            $depFileId = $dep['file_id'] ?? null;
            $depSource = $dep['source'];

            // Skip if dependency is the mod itself (shouldn't happen, but safety check)
            if ($depSource === $source && $depModId == $modId) {
                continue;
            }

            // Check if dependency already exists in mod pack
            $existingItem = null;
            if ($depSource === 'curseforge' && is_numeric($depModId)) {
                $existingItem = ModPackItem::where('mod_pack_id', $modPack->id)
                    ->where('curseforge_mod_id', $depModId)
                    ->first();
            } elseif ($depSource === 'modrinth') {
                $existingItem = ModPackItem::where('mod_pack_id', $modPack->id)
                    ->where('modrinth_project_id', $depModId)
                    ->first();
            }

            // Skip if already exists
            if ($existingItem) {
                continue;
            }

            // Get mod details
            $depMod = $modService->getMod($depModId, $depSource);
            if (! $depMod) {
                continue;
            }

            // If file ID not provided, get the latest compatible version
            if (! $depFileId) {
                $depFiles = $modService->getModFiles(
                    $depModId,
                    $modPack->minecraft_version,
                    $modPack->software,
                    $depSource
                );
                if (empty($depFiles)) {
                    continue;
                }
                $depFile = $depFiles[0];
                $depFileId = $depFile['id'];
            } else {
                // Get file data to get version string
                $depFile = $modService->getModFile($depModId, $depFileId, $depSource);
                if (! $depFile) {
                    continue;
                }
            }

            // Get mod name and version string
            $depModName = $depMod['name'] ?? $depMod['title'] ?? 'Unknown Mod';
            $depModSlug = $depMod['slug'] ?? null;

            if ($depSource === 'curseforge') {
                $depModVersion = $depFile['displayName'] ?? $depFile['fileName'] ?? 'Unknown Version';
            } else {
                $depModVersion = $depFile['version_number'] ?? $depFile['name'] ?? $depFile['id'] ?? 'Unknown Version';
            }

            // Add dependency to mod pack (mark as auto-added)
            ModPackItem::create([
                'mod_pack_id' => $modPack->id,
                'mod_name' => $depModName,
                'mod_version' => $depModVersion,
                'curseforge_mod_id' => $depSource === 'curseforge' ? $depModId : null,
                'curseforge_file_id' => $depSource === 'curseforge' ? $depFileId : null,
                'curseforge_slug' => ($depSource === 'curseforge' && $depModSlug) ? $depModSlug : null,
                'modrinth_project_id' => $depSource === 'modrinth' ? $depModId : null,
                'modrinth_version_id' => $depSource === 'modrinth' ? $depFileId : null,
                'modrinth_slug' => ($depSource === 'modrinth' && $depModSlug) ? $depModSlug : null,
                'source' => $depSource,
                'is_auto_added' => true,
                'sort_order' => ++$maxSortOrder,
            ]);

            $addedCount++;

            // Recursively add dependencies of this dependency
            $addedCount += $this->addRequiredDependencies($modPack, $depModId, $depFileId, $depSource, $visited);
        }

        return $addedCount;
    }

    /**
     * Store a new mod item for a mod pack.
     */
    public function storeItem(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'mod_name' => ['required', 'string', 'max:255'],
            'mod_version' => ['required', 'string', 'max:255'],
            'curseforge_mod_id' => ['nullable', 'integer'],
            'curseforge_file_id' => ['nullable', 'integer'],
            'curseforge_slug' => ['nullable', 'string', 'max:255'],
            'modrinth_project_id' => ['nullable', 'string', 'max:255'],
            'modrinth_version_id' => ['nullable', 'string', 'max:255'],
            'modrinth_slug' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'in:curseforge,modrinth'],
        ]);

        // Determine source if not provided
        $source = $validated['source'] ?? null;
        if (! $source) {
            if (! empty($validated['curseforge_mod_id'])) {
                $source = 'curseforge';
            } elseif (! empty($validated['modrinth_project_id'])) {
                $source = 'modrinth';
            }
        }

        // Check if mod is already in the mod pack
        if ($source === 'curseforge' && ! empty($validated['curseforge_mod_id'])) {
            $existingItem = ModPackItem::where('mod_pack_id', $modPack->id)
                ->where('curseforge_mod_id', $validated['curseforge_mod_id'])
                ->first();

            if ($existingItem) {
                return back()->withErrors([
                    'curseforge_mod_id' => __('messages.modpack.mod_already_added'),
                ]);
            }
        } elseif ($source === 'modrinth' && ! empty($validated['modrinth_project_id'])) {
            $existingItem = ModPackItem::where('mod_pack_id', $modPack->id)
                ->where('modrinth_project_id', $validated['modrinth_project_id'])
                ->first();

            if ($existingItem) {
                return back()->withErrors([
                    'modrinth_project_id' => __('messages.modpack.mod_already_added'),
                ]);
            }
        }

        $maxSortOrder = (int) (ModPackItem::where('mod_pack_id', $modPack->id)->max('sort_order') ?? 0);

        // Create the mod item
        $modId = $source === 'curseforge' ? ($validated['curseforge_mod_id'] ?? null) : ($validated['modrinth_project_id'] ?? null);
        $fileId = $source === 'curseforge' ? ($validated['curseforge_file_id'] ?? null) : ($validated['modrinth_version_id'] ?? null);

        ModPackItem::create([
            'mod_pack_id' => $modPack->id,
            'mod_name' => $validated['mod_name'],
            'mod_version' => $validated['mod_version'],
            'curseforge_mod_id' => $validated['curseforge_mod_id'] ?? null,
            'curseforge_file_id' => $validated['curseforge_file_id'] ?? null,
            'curseforge_slug' => $validated['curseforge_slug'] ?? null,
            'modrinth_project_id' => $validated['modrinth_project_id'] ?? null,
            'modrinth_version_id' => $validated['modrinth_version_id'] ?? null,
            'modrinth_slug' => $validated['modrinth_slug'] ?? null,
            'source' => $source,
            'sort_order' => $maxSortOrder + 1,
        ]);

        // Automatically add required dependencies
        if ($modId && $fileId) {
            $this->addRequiredDependencies($modPack, $modId, $fileId, $source);
        }

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Update a mod item in a mod pack.
     */
    public function updateItem(Request $request, string $id, string $itemId)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);
        $item = ModPackItem::where('mod_pack_id', $modPack->id)->findOrFail($itemId);

        $validated = $request->validate([
            'mod_name' => ['required', 'string', 'max:255'],
            'mod_version' => ['required', 'string', 'max:255'],
            'curseforge_mod_id' => ['nullable', 'integer'],
            'curseforge_file_id' => ['nullable', 'integer'],
            'curseforge_slug' => ['nullable', 'string', 'max:255'],
            'modrinth_project_id' => ['nullable', 'string', 'max:255'],
            'modrinth_version_id' => ['nullable', 'string', 'max:255'],
            'modrinth_slug' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'in:curseforge,modrinth'],
        ]);

        $item->update($validated);

        // Determine source and IDs after update
        $source = $item->source;
        if (! $source) {
            if ($item->curseforge_mod_id) {
                $source = 'curseforge';
            } elseif ($item->modrinth_project_id) {
                $source = 'modrinth';
            }
        }

        // Automatically add required dependencies if mod ID and file ID are available
        if ($source) {
            $modId = $source === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
            $fileId = $source === 'curseforge' ? $item->curseforge_file_id : $item->modrinth_version_id;

            if ($modId && $fileId) {
                $this->addRequiredDependencies($modPack, $modId, $fileId, $source);
            }
        }

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Check if a mod is required by any other mod in the mod pack.
     *
     * @param  ModPack  $modPack  The mod pack
     * @param  ModPackItem  $item  The mod item to check
     * @return array Array with 'is_required' boolean and 'required_by' array of mod names
     */
    private function isModRequiredByOthers(ModPack $modPack, ModPackItem $item): array
    {
        $modService = new ModService;
        $requiredBy = [];

        $itemSource = $item->source;
        if (! $itemSource) {
            if ($item->curseforge_mod_id) {
                $itemSource = 'curseforge';
            } elseif ($item->modrinth_project_id) {
                $itemSource = 'modrinth';
            } else {
                return ['is_required' => false, 'required_by' => []];
            }
        }

        $itemModId = $itemSource === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;

        if (! $itemModId) {
            return ['is_required' => false, 'required_by' => []];
        }

        // Check all other mods in the pack
        foreach ($modPack->items as $otherItem) {
            // Skip the mod itself
            if ($otherItem->id === $item->id) {
                continue;
            }

            $otherSource = $otherItem->source;
            if (! $otherSource) {
                if ($otherItem->curseforge_mod_id) {
                    $otherSource = 'curseforge';
                } elseif ($otherItem->modrinth_project_id) {
                    $otherSource = 'modrinth';
                } else {
                    continue;
                }
            }

            $otherModId = $otherSource === 'curseforge' ? $otherItem->curseforge_mod_id : $otherItem->modrinth_project_id;
            $otherFileId = $otherSource === 'curseforge' ? $otherItem->curseforge_file_id : $otherItem->modrinth_version_id;

            if (! $otherModId || ! $otherFileId) {
                continue;
            }

            // Get dependencies for this other mod
            $fileData = $modService->getModFile($otherModId, $otherFileId, $otherSource);
            if (! $fileData) {
                continue;
            }

            $dependencies = $modService->getFileDependencies($fileData, $otherSource);

            // Check if this other mod depends on the mod being removed
            foreach ($dependencies['required'] ?? [] as $requiredModId) {
                if ($otherSource === $itemSource && $requiredModId == $itemModId) {
                    $requiredBy[] = $otherItem->mod_name;
                    break;
                }
            }
        }

        return [
            'is_required' => ! empty($requiredBy),
            'required_by' => $requiredBy,
        ];
    }

    /**
     * Check and remove orphaned auto-added mods (mods that are no longer needed by any other mod).
     *
     * @param  ModPack  $modPack  The mod pack
     * @return int Number of orphaned mods removed
     */
    private function removeOrphanedAutoAddedMods(ModPack $modPack): int
    {
        $modService = new ModService;
        $removedCount = 0;
        $changed = true;

        // Keep iterating until no more orphaned mods are found
        while ($changed) {
            $changed = false;

            // Refresh mod pack to get current items
            $modPack->load('items');

            // Get all auto-added mods
            $autoAddedMods = ModPackItem::where('mod_pack_id', $modPack->id)
                ->where('is_auto_added', true)
                ->get();

            foreach ($autoAddedMods as $autoAddedMod) {
                $autoAddedSource = $autoAddedMod->source;
                if (! $autoAddedSource) {
                    if ($autoAddedMod->curseforge_mod_id) {
                        $autoAddedSource = 'curseforge';
                    } elseif ($autoAddedMod->modrinth_project_id) {
                        $autoAddedSource = 'modrinth';
                    } else {
                        continue;
                    }
                }

                $autoAddedModId = $autoAddedSource === 'curseforge'
                    ? $autoAddedMod->curseforge_mod_id
                    : $autoAddedMod->modrinth_project_id;

                if (! $autoAddedModId) {
                    continue;
                }

                // Check if any remaining mod depends on this auto-added mod
                $isOrphaned = true;
                foreach ($modPack->items as $item) {
                    // Skip the auto-added mod itself
                    if ($item->id === $autoAddedMod->id) {
                        continue;
                    }

                    $itemSource = $item->source;
                    if (! $itemSource) {
                        if ($item->curseforge_mod_id) {
                            $itemSource = 'curseforge';
                        } elseif ($item->modrinth_project_id) {
                            $itemSource = 'modrinth';
                        } else {
                            continue;
                        }
                    }

                    $itemModId = $itemSource === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
                    $itemFileId = $itemSource === 'curseforge' ? $item->curseforge_file_id : $item->modrinth_version_id;

                    if (! $itemModId || ! $itemFileId) {
                        continue;
                    }

                    // Get dependencies for this item
                    $fileData = $modService->getModFile($itemModId, $itemFileId, $itemSource);
                    if (! $fileData) {
                        continue;
                    }

                    $dependencies = $modService->getFileDependencies($fileData, $itemSource);

                    // Check if this item depends on the auto-added mod
                    foreach ($dependencies['required'] ?? [] as $requiredModId) {
                        if ($itemSource === $autoAddedSource && $requiredModId == $autoAddedModId) {
                            $isOrphaned = false;
                            break 2; // Break out of both loops
                        }
                    }
                }

                // If orphaned, remove it
                if ($isOrphaned) {
                    $autoAddedMod->delete();
                    $removedCount++;
                    $changed = true;
                    // Break to restart the loop and check again
                    break;
                }
            }
        }

        return $removedCount;
    }

    /**
     * Remove a mod item from a mod pack.
     */
    public function destroyItem(string $id, string $itemId)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);
        $item = ModPackItem::where('mod_pack_id', $modPack->id)->findOrFail($itemId);

        // Check if this mod is required by other mods
        $requiredCheck = $this->isModRequiredByOthers($modPack, $item);
        if ($requiredCheck['is_required']) {
            $requiredByNames = implode(', ', $requiredCheck['required_by']);

            return redirect()->route('mod-packs.show', $modPack->id)
                ->with('error', __('messages.modpack.cannot_remove_required', [
                    'name' => $item->mod_name,
                    'required_by' => $requiredByNames,
                ]));
        }

        $item->delete();

        // Clean up orphaned auto-added mods
        $this->removeOrphanedAutoAddedMods($modPack);

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Duplicate a mod pack with all its items.
     */
    public function duplicate(string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        // Create new mod pack with same data but add " (Clone)" to the name
        $newModPack = ModPack::create([
            'user_id' => Auth::id(),
            'name' => $modPack->name.' (Clone)',
            'minecraft_version' => $modPack->minecraft_version,
            'software' => $modPack->software,
            'description' => $modPack->description,
        ]);

        // Copy all items from the original mod pack
        foreach ($modPack->items as $item) {
            ModPackItem::create([
                'mod_pack_id' => $newModPack->id,
                'mod_name' => $item->mod_name,
                'mod_version' => $item->mod_version,
                'curseforge_mod_id' => $item->curseforge_mod_id,
                'curseforge_file_id' => $item->curseforge_file_id,
                'curseforge_slug' => $item->curseforge_slug,
                'modrinth_project_id' => $item->modrinth_project_id,
                'modrinth_version_id' => $item->modrinth_version_id,
                'modrinth_slug' => $item->modrinth_slug,
                'source' => $item->source,
                'sort_order' => $item->sort_order,
            ]);
        }

        return redirect()->route('mod-packs.show', $newModPack->id);
    }

    /**
     * Get download links for all mod items in a mod pack.
     */
    public function getDownloadLinks(string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $modService = new ModService;
        $downloadLinks = [];

        foreach ($modPack->items as $item) {
            $downloadInfo = $this->getItemDownloadInfo($item, $modService);

            if ($downloadInfo) {
                $downloadLinks[] = [
                    'item_id' => $item->id,
                    'mod_name' => $item->mod_name,
                    'mod_version' => $item->mod_version,
                    'download_url' => $downloadInfo['url'],
                    'filename' => $downloadInfo['filename'],
                ];
            }
        }

        // Increment downloads count for ZIP download
        $modPack->increment('downloads_count');

        return response()->json([
            'data' => $downloadLinks,
        ]);
    }

    /**
     * Get download links for selected mod items in a mod pack.
     */
    public function getBulkDownloadLinks(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:mod_pack_items,id'],
        ]);

        // Verify all items belong to this mod pack
        $itemIds = $validated['item_ids'];
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        $modService = new ModService;
        $downloadLinks = [];

        foreach ($items as $item) {
            $downloadInfo = $this->getItemDownloadInfo($item, $modService);

            if ($downloadInfo) {
                $downloadLinks[] = [
                    'item_id' => $item->id,
                    'mod_name' => $item->mod_name,
                    'mod_version' => $item->mod_version,
                    'download_url' => $downloadInfo['url'],
                    'filename' => $downloadInfo['filename'],
                ];
            }
        }

        // Increment downloads count for ZIP download
        $modPack->increment('downloads_count');

        return response()->json([
            'data' => $downloadLinks,
        ]);
    }

    /**
     * Delete multiple mod items from a mod pack.
     */
    public function destroyBulkItems(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:mod_pack_items,id'],
        ]);

        // Verify all items belong to this mod pack
        $itemIds = $validated['item_ids'];
        $modPack->load('items');
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        // Check if any of the mods being deleted are required by other mods
        $requiredMods = [];
        foreach ($items as $item) {
            $requiredCheck = $this->isModRequiredByOthers($modPack, $item);
            if ($requiredCheck['is_required']) {
                // Only check against mods that are NOT being deleted
                $requiredBy = [];
                foreach ($requiredCheck['required_by'] as $requiredByName) {
                    // Check if the mod requiring this one is also being deleted
                    $isAlsoBeingDeleted = false;
                    foreach ($items as $otherItem) {
                        if ($otherItem->mod_name === $requiredByName && $otherItem->id !== $item->id) {
                            $isAlsoBeingDeleted = true;
                            break;
                        }
                    }
                    if (! $isAlsoBeingDeleted) {
                        $requiredBy[] = $requiredByName;
                    }
                }
                if (! empty($requiredBy)) {
                    $requiredMods[] = [
                        'name' => $item->mod_name,
                        'required_by' => implode(', ', $requiredBy),
                    ];
                }
            }
        }

        if (! empty($requiredMods)) {
            $errorMessage = __('messages.modpack.cannot_remove_required_bulk', [
                'count' => count($requiredMods),
            ]);
            $errorMessage .= ' '.implode('; ', array_map(function ($mod) {
                return __('messages.modpack.cannot_remove_required', [
                    'name' => $mod['name'],
                    'required_by' => $mod['required_by'],
                ]);
            }, $requiredMods));

            return response()->json([
                'error' => $errorMessage,
            ], 400);
        }

        // Delete all items
        ModPackItem::whereIn('id', $itemIds)->delete();

        // Clean up orphaned auto-added mods
        $this->removeOrphanedAutoAddedMods($modPack);

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Get download link for a specific mod item.
     */
    public function getItemDownloadLink(string $id, string $itemId)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);
        $item = ModPackItem::where('mod_pack_id', $modPack->id)->findOrFail($itemId);

        // Check if item has required metadata before attempting to get download info
        $hasMetadata = false;
        if ($item->source === 'curseforge' || ($item->curseforge_mod_id && $item->curseforge_file_id)) {
            $hasMetadata = true;
        } elseif ($item->source === 'modrinth' || ($item->modrinth_project_id && $item->modrinth_version_id)) {
            $hasMetadata = true;
        }

        if (! $hasMetadata) {
            return response()->json([
                'error' => __('messages.modpack.no_download_info'),
            ], 404);
        }

        $modService = new ModService;
        $downloadInfo = $this->getItemDownloadInfo($item, $modService);

        if (! $downloadInfo) {
            return response()->json([
                'error' => __('messages.modpack.unable_to_retrieve_download'),
            ], 404);
        }

        // Increment downloads count
        $modPack->increment('downloads_count');

        return response()->json([
            'data' => [
                'item_id' => $item->id,
                'mod_name' => $item->mod_name,
                'mod_version' => $item->mod_version,
                'download_url' => $downloadInfo['url'],
                'filename' => $downloadInfo['filename'],
            ],
        ]);
    }

    /**
     * Change version of a mod pack by creating a new mod pack with updated versions.
     */
    public function changeVersion(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $validated = $request->validate([
            'minecraft_version' => ['required', 'string', 'max:255'],
            'software' => ['required', 'string', 'in:forge,fabric,quilt,neoforge'],
        ]);

        $newMinecraftVersion = $validated['minecraft_version'];
        $newSoftware = $validated['software'];

        // If the version and software are the same, just redirect back
        if ($modPack->minecraft_version === $newMinecraftVersion && $modPack->software === $newSoftware) {
            return redirect()->route('mod-packs.show', $modPack->id);
        }

        $modService = new ModService;
        $modsWithoutMatchingVersion = [];

        // Check each mod item to see if it has a matching version for the new MC version
        foreach ($modPack->items as $item) {
            $source = $item->source;
            if (! $source) {
                // Determine source from item data
                if ($item->curseforge_mod_id) {
                    $source = 'curseforge';
                } elseif ($item->modrinth_project_id) {
                    $source = 'modrinth';
                } else {
                    // Skip items without platform metadata (they can't be validated)
                    continue;
                }
            }

            $modId = $source === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
            if (! $modId) {
                continue;
            }

            $files = $modService->getModFiles(
                $modId,
                $newMinecraftVersion,
                $newSoftware,
                $source
            );

            // If no files found for this mod with the new version, add to error list
            if (empty($files)) {
                $modsWithoutMatchingVersion[] = $item->mod_name;
            }
        }

        // If any mods don't have matching versions, return error
        if (! empty($modsWithoutMatchingVersion)) {
            return back()->withErrors([
                'version_change' => __('messages.modpack.mods_without_version', [
                    'version' => $newMinecraftVersion,
                    'software' => $newSoftware,
                    'mods' => implode(', ', $modsWithoutMatchingVersion),
                ]),
                'mods_without_version' => $modsWithoutMatchingVersion,
            ]);
        }

        // Create new mod pack with updated name
        $newModPackName = $modPack->name.' (Updated to '.$newMinecraftVersion.' '.ucfirst($newSoftware).')';
        $newModPack = ModPack::create([
            'user_id' => Auth::id(),
            'name' => $newModPackName,
            'minecraft_version' => $newMinecraftVersion,
            'software' => $newSoftware,
            'description' => $modPack->description,
        ]);

        // Copy all mod items with new versions
        $sortOrder = 1;
        foreach ($modPack->items as $item) {
            $source = $item->source;
            if (! $source) {
                // Determine source from item data
                if ($item->curseforge_mod_id) {
                    $source = 'curseforge';
                } elseif ($item->modrinth_project_id) {
                    $source = 'modrinth';
                } else {
                    // For items without platform metadata, copy as-is
                    ModPackItem::create([
                        'mod_pack_id' => $newModPack->id,
                        'mod_name' => $item->mod_name,
                        'mod_version' => $item->mod_version,
                        'curseforge_mod_id' => $item->curseforge_mod_id,
                        'curseforge_file_id' => $item->curseforge_file_id,
                        'curseforge_slug' => $item->curseforge_slug,
                        'modrinth_project_id' => $item->modrinth_project_id,
                        'modrinth_version_id' => $item->modrinth_version_id,
                        'modrinth_slug' => $item->modrinth_slug,
                        'source' => $item->source,
                        'sort_order' => $sortOrder++,
                    ]);

                    continue;
                }
            }

            $modId = $source === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
            if (! $modId) {
                continue;
            }

            // Get the latest file/version for the new version
            $latestFile = $modService->getLatestModFile(
                $modId,
                $newMinecraftVersion,
                $newSoftware,
                $source
            );

            if ($latestFile) {
                if ($source === 'curseforge') {
                    ModPackItem::create([
                        'mod_pack_id' => $newModPack->id,
                        'mod_name' => $item->mod_name,
                        'mod_version' => $latestFile['displayName'] ?? $latestFile['fileName'] ?? $item->mod_version,
                        'curseforge_mod_id' => $item->curseforge_mod_id,
                        'curseforge_file_id' => $latestFile['id'],
                        'curseforge_slug' => $item->curseforge_slug,
                        'modrinth_project_id' => null,
                        'modrinth_version_id' => null,
                        'modrinth_slug' => null,
                        'source' => 'curseforge',
                        'sort_order' => $sortOrder++,
                    ]);
                } elseif ($source === 'modrinth') {
                    ModPackItem::create([
                        'mod_pack_id' => $newModPack->id,
                        'mod_name' => $item->mod_name,
                        'mod_version' => $latestFile['version_number'] ?? $latestFile['name'] ?? $item->mod_version,
                        'curseforge_mod_id' => null,
                        'curseforge_file_id' => null,
                        'curseforge_slug' => null,
                        'modrinth_project_id' => $item->modrinth_project_id,
                        'modrinth_version_id' => $latestFile['id'],
                        'modrinth_slug' => $item->modrinth_slug,
                        'source' => 'modrinth',
                        'sort_order' => $sortOrder++,
                    ]);
                }
            } else {
                \Log::warning('getLatestModFile returned null for mod', [
                    'mod_id' => $modId,
                    'mod_name' => $item->mod_name,
                    'source' => $source,
                    'minecraft_version' => $newMinecraftVersion,
                    'software' => $newSoftware,
                ]);
            }
        }

        return redirect()->route('mod-packs.show', $newModPack->id);
    }

    /**
     * Set a reminder for when all mods become available for a target Minecraft version.
     */
    public function setReminder(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'minecraft_version' => ['required', 'string', 'max:255'],
            'software' => ['required', 'string', 'in:forge,fabric,quilt,neoforge'],
        ]);

        $modPack->update([
            'minecraft_update_reminder_version' => $validated['minecraft_version'],
            'minecraft_update_reminder_software' => $validated['software'],
        ]);

        return response()->json([
            'message' => 'Reminder set successfully',
        ]);
    }

    /**
     * Cancel a reminder for Minecraft version update.
     */
    public function cancelReminder(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $modPack->update([
            'minecraft_update_reminder_version' => null,
            'minecraft_update_reminder_software' => null,
        ]);

        return response()->json([
            'message' => 'Reminder cancelled successfully',
        ]);
    }

    /**
     * Proxy endpoint to download mod files (bypasses CORS).
     * This is a simple pass-through proxy - no server-side zip generation.
     * The client still creates the ZIP file.
     */
    public function proxyDownload(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $validated['url'];

        // Verify the URL is from CurseForge CDN (security check)
        $allowedDomains = [
            'mediafilez.forgecdn.net',
            'edge.forgecdn.net',
            'cdn.modrinth.com', // In case we add Modrinth support later
        ];

        $parsedUrl = parse_url($url);
        if (! isset($parsedUrl['host']) || ! in_array($parsedUrl['host'], $allowedDomains)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_download_url'),
            ], 400);
        }

        try {
            // Download the file from CurseForge CDN
            $response = Http::timeout(60) // Longer timeout for large files
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($url);

            if (! $response->successful()) {
                \Log::warning('Proxy download failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'error' => __('messages.modpack.download_failed'),
                ], $response->status());
            }

            // Get the content type from the response or default to binary
            $contentType = $response->header('Content-Type') ?: 'application/java-archive';

            // Return the file content with appropriate headers
            return response($response->body(), 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline', // Don't force download, let client handle it
                'Cache-Control' => 'no-cache', // Don't cache proxy responses
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Proxy download connection error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => __('messages.modpack.connection_timeout'),
            ], 504);
        } catch (\Exception $e) {
            \Log::error('Proxy download error', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => __('messages.modpack.proxy_download_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Generate or regenerate a share token for a mod pack.
     */
    public function generateShareToken(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $regenerate = $request->boolean('regenerate', false);

        if ($regenerate || ! $modPack->share_token) {
            $token = $modPack->regenerateShareToken();
        } else {
            $token = $modPack->share_token;
        }

        return response()->json([
            'share_token' => $token,
            'share_url' => $modPack->getShareUrl(),
        ]);
    }

    /**
     * Display a shared mod pack (public view, no authentication required).
     */
    public function showShared(string $token)
    {
        $modPack = ModPack::where('share_token', $token)
            ->with(['items', 'user'])
            ->firstOrFail();

        $modService = new ModService;
        $gameVersions = $modService->getGameVersions();
        $modLoaders = $modService->getModLoaders();

        // Check if the current user owns this mod pack
        $isOwner = Auth::check() && $modPack->user_id === Auth::id();

        // Get sharer name
        $sharerName = $modPack->user->name ?? 'Unknown';

        return Inertia::render('ModPacks/Shared', [
            'modPack' => $modPack,
            'gameVersions' => $gameVersions,
            'modLoaders' => $modLoaders,
            'isOwner' => $isOwner,
            'sharerName' => $sharerName,
        ]);
    }

    /**
     * Add a shared mod pack to the authenticated user's collection.
     */
    public function addToCollection(string $token)
    {
        $sharedModPack = ModPack::where('share_token', $token)
            ->with(['items', 'user'])
            ->firstOrFail();

        // Get the sharer's name
        $sharerName = $sharedModPack->user->name ?? 'Unknown';

        // Create a copy of the mod pack for the current user with sharer name appended
        $newModPack = ModPack::create([
            'user_id' => Auth::id(),
            'name' => $sharedModPack->name.' (Shared by '.$sharerName.')',
            'minecraft_version' => $sharedModPack->minecraft_version,
            'software' => $sharedModPack->software,
            'description' => $sharedModPack->description,
        ]);

        // Copy all mod items
        foreach ($sharedModPack->items as $item) {
            ModPackItem::create([
                'mod_pack_id' => $newModPack->id,
                'mod_name' => $item->mod_name,
                'mod_version' => $item->mod_version,
                'curseforge_mod_id' => $item->curseforge_mod_id,
                'curseforge_file_id' => $item->curseforge_file_id,
                'curseforge_slug' => $item->curseforge_slug,
                'modrinth_project_id' => $item->modrinth_project_id,
                'modrinth_version_id' => $item->modrinth_version_id,
                'modrinth_slug' => $item->modrinth_slug,
                'source' => $item->source,
                'sort_order' => $item->sort_order,
            ]);
        }

        return redirect()->route('mod-packs.show', $newModPack->id)->with('success', __('messages.modpack.added_to_collection'));
    }

    /**
     * Get download links for all mod items in a shared mod pack.
     */
    public function getSharedDownloadLinks(string $token)
    {
        $modPack = ModPack::where('share_token', $token)
            ->with('items')
            ->firstOrFail();

        $modService = new ModService;
        $downloadLinks = [];

        foreach ($modPack->items as $item) {
            $downloadInfo = $this->getItemDownloadInfo($item, $modService);

            if ($downloadInfo) {
                $downloadLinks[] = [
                    'item_id' => $item->id,
                    'mod_name' => $item->mod_name,
                    'mod_version' => $item->mod_version,
                    'download_url' => $downloadInfo['url'],
                    'filename' => $downloadInfo['filename'],
                ];
            }
        }

        // Increment downloads count for ZIP download
        $modPack->increment('downloads_count');

        return response()->json([
            'data' => $downloadLinks,
        ]);
    }

    /**
     * Get download links for selected mod items in a shared mod pack.
     */
    public function getSharedBulkDownloadLinks(Request $request, string $token)
    {
        $modPack = ModPack::where('share_token', $token)
            ->with('items')
            ->firstOrFail();

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:mod_pack_items,id'],
        ]);

        // Verify all items belong to this mod pack
        $itemIds = $validated['item_ids'];
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        $modService = new ModService;
        $downloadLinks = [];

        foreach ($items as $item) {
            $downloadInfo = $this->getItemDownloadInfo($item, $modService);

            if ($downloadInfo) {
                $downloadLinks[] = [
                    'item_id' => $item->id,
                    'mod_name' => $item->mod_name,
                    'mod_version' => $item->mod_version,
                    'download_url' => $downloadInfo['url'],
                    'filename' => $downloadInfo['filename'],
                ];
            }
        }

        // Increment downloads count for ZIP download
        $modPack->increment('downloads_count');

        return response()->json([
            'data' => $downloadLinks,
        ]);
    }

    /**
     * Get download link for a specific mod item in a shared mod pack.
     */
    public function getSharedItemDownloadLink(string $token, string $itemId)
    {
        $modPack = ModPack::where('share_token', $token)
            ->with('items')
            ->firstOrFail();

        $item = ModPackItem::where('mod_pack_id', $modPack->id)->findOrFail($itemId);

        // Check if item has required metadata before attempting to get download info
        $hasMetadata = false;
        if ($item->source === 'curseforge' || ($item->curseforge_mod_id && $item->curseforge_file_id)) {
            $hasMetadata = true;
        } elseif ($item->source === 'modrinth' || ($item->modrinth_project_id && $item->modrinth_version_id)) {
            $hasMetadata = true;
        }

        if (! $hasMetadata) {
            return response()->json([
                'error' => __('messages.modpack.no_download_info'),
            ], 404);
        }

        $modService = new ModService;
        $downloadInfo = $this->getItemDownloadInfo($item, $modService);

        if (! $downloadInfo) {
            return response()->json([
                'error' => __('messages.modpack.unable_to_retrieve_download'),
            ], 404);
        }

        // Increment downloads count
        $modPack->increment('downloads_count');

        return response()->json([
            'data' => [
                'item_id' => $item->id,
                'mod_name' => $item->mod_name,
                'mod_version' => $item->mod_version,
                'download_url' => $downloadInfo['url'],
                'filename' => $downloadInfo['filename'],
            ],
        ]);
    }

    /**
     * Proxy endpoint to download mod files for shared modpacks (bypasses CORS).
     */
    public function sharedProxyDownload(Request $request, string $token)
    {
        $modPack = ModPack::where('share_token', $token)->firstOrFail();

        $validated = $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $validated['url'];

        // Verify the URL is from CurseForge CDN (security check)
        $allowedDomains = [
            'mediafilez.forgecdn.net',
            'edge.forgecdn.net',
            'cdn.modrinth.com', // In case we add Modrinth support later
        ];

        $parsedUrl = parse_url($url);
        if (! isset($parsedUrl['host']) || ! in_array($parsedUrl['host'], $allowedDomains)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_download_url'),
            ], 400);
        }

        try {
            // Download the file from CurseForge CDN
            $response = Http::timeout(60) // Longer timeout for large files
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($url);

            if (! $response->successful()) {
                \Log::warning('Proxy download failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'error' => __('messages.modpack.download_failed'),
                ], $response->status());
            }

            // Get the content type from the response or default to binary
            $contentType = $response->header('Content-Type') ?: 'application/java-archive';

            // Return the file content with appropriate headers
            return response($response->body(), 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline', // Don't force download, let client handle it
                'Cache-Control' => 'no-cache', // Don't cache proxy responses
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Proxy download connection error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => __('messages.modpack.connection_timeout'),
            ], 504);
        } catch (\Exception $e) {
            \Log::error('Proxy download error', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => __('messages.modpack.proxy_download_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Preview available updates for all mod items in a mod pack.
     */
    public function previewAllItemsToLatest(string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $modService = new ModService;
        $updates = [];

        foreach ($modPack->items as $item) {
            $latestVersionData = $this->getItemLatestVersion(
                $item,
                $modPack->minecraft_version,
                $modPack->software,
                $modService
            );

            if ($latestVersionData) {
                // Handle both CurseForge and Modrinth response formats
                $source = $item->source ?? ($item->curseforge_mod_id ? 'curseforge' : 'modrinth');
                if ($source === 'curseforge') {
                    $latestVersion = $latestVersionData['displayName'] ?? $latestVersionData['fileName'] ?? null;
                    $latestId = $latestVersionData['id'] ?? null;
                    $fileDate = $latestVersionData['fileDate'] ?? null;
                } else {
                    $latestVersion = $latestVersionData['version_number'] ?? $latestVersionData['name'] ?? null;
                    $latestId = $latestVersionData['id'] ?? null;
                    $fileDate = $latestVersionData['date_published'] ?? null;
                }

                $currentVersion = $item->mod_version;

                // Only include if there's an update available
                if ($latestVersion && $latestVersion !== $currentVersion) {
                    $updates[] = [
                        'item_id' => $item->id,
                        'mod_name' => $item->mod_name,
                        'current_version' => $currentVersion,
                        'latest_version' => $latestVersion,
                        'latest_file_id' => $latestId,
                        'file_date' => $fileDate,
                        'source' => $source,
                    ];
                }
            }
        }

        return response()->json([
            'updates' => $updates,
            'total_count' => count($updates),
        ]);
    }

    /**
     * Preview available updates for selected mod items.
     */
    public function previewBulkItemsToLatest(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:mod_pack_items,id'],
        ]);

        // Verify all items belong to this mod pack
        $itemIds = $validated['item_ids'];
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        $modService = new ModService;
        $updates = [];

        foreach ($items as $item) {
            $latestVersionData = $this->getItemLatestVersion(
                $item,
                $modPack->minecraft_version,
                $modPack->software,
                $modService
            );

            if ($latestVersionData) {
                // Handle both CurseForge and Modrinth response formats
                $source = $item->source ?? ($item->curseforge_mod_id ? 'curseforge' : 'modrinth');
                if ($source === 'curseforge') {
                    $latestVersion = $latestVersionData['displayName'] ?? $latestVersionData['fileName'] ?? null;
                    $latestId = $latestVersionData['id'] ?? null;
                    $fileDate = $latestVersionData['fileDate'] ?? null;
                } else {
                    $latestVersion = $latestVersionData['version_number'] ?? $latestVersionData['name'] ?? null;
                    $latestId = $latestVersionData['id'] ?? null;
                    $fileDate = $latestVersionData['date_published'] ?? null;
                }

                $currentVersion = $item->mod_version;

                // Only include if there's an update available
                if ($latestVersion && $latestVersion !== $currentVersion) {
                    $updates[] = [
                        'item_id' => $item->id,
                        'mod_name' => $item->mod_name,
                        'current_version' => $currentVersion,
                        'latest_version' => $latestVersion,
                        'latest_file_id' => $latestId,
                        'file_date' => $fileDate,
                        'source' => $source,
                    ];
                }
            }
        }

        return response()->json([
            'updates' => $updates,
            'total_count' => count($updates),
        ]);
    }

    /**
     * Update all mod items in a mod pack to their latest versions.
     */
    public function updateAllItemsToLatest(string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $modService = new ModService;
        $updatedCount = 0;
        $failedCount = 0;

        foreach ($modPack->items as $item) {
            $latestVersionData = $this->getItemLatestVersion(
                $item,
                $modPack->minecraft_version,
                $modPack->software,
                $modService
            );

            if ($latestVersionData) {
                $source = $item->source ?? ($item->curseforge_mod_id ? 'curseforge' : 'modrinth');
                $updateData = [];

                if ($source === 'curseforge') {
                    $updateData = [
                        'mod_version' => $latestVersionData['displayName'] ?? $latestVersionData['fileName'] ?? $item->mod_version,
                        'curseforge_file_id' => $latestVersionData['id'],
                    ];
                } elseif ($source === 'modrinth') {
                    $updateData = [
                        'mod_version' => $latestVersionData['version_number'] ?? $latestVersionData['name'] ?? $item->mod_version,
                        'modrinth_version_id' => $latestVersionData['id'],
                    ];
                }

                if (! empty($updateData)) {
                    $item->update($updateData);
                    $updatedCount++;
                } else {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'failed_count' => $failedCount,
        ]);
    }

    /**
     * Update selected mod items to their latest versions.
     */
    public function updateBulkItemsToLatest(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:mod_pack_items,id'],
        ]);

        // Verify all items belong to this mod pack
        $itemIds = $validated['item_ids'];
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        $modService = new ModService;
        $updatedCount = 0;
        $failedCount = 0;

        foreach ($items as $item) {
            $latestVersionData = $this->getItemLatestVersion(
                $item,
                $modPack->minecraft_version,
                $modPack->software,
                $modService
            );

            if ($latestVersionData) {
                $source = $item->source ?? ($item->curseforge_mod_id ? 'curseforge' : 'modrinth');
                $updateData = [];

                if ($source === 'curseforge') {
                    $updateData = [
                        'mod_version' => $latestVersionData['displayName'] ?? $latestVersionData['fileName'] ?? $item->mod_version,
                        'curseforge_file_id' => $latestVersionData['id'],
                    ];
                } elseif ($source === 'modrinth') {
                    $updateData = [
                        'mod_version' => $latestVersionData['version_number'] ?? $latestVersionData['name'] ?? $item->mod_version,
                        'modrinth_version_id' => $latestVersionData['id'],
                    ];
                }

                if (! empty($updateData)) {
                    $item->update($updateData);
                    $updatedCount++;
                } else {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'failed_count' => $failedCount,
        ]);
    }

    /**
     * Reorder mod items in a mod pack.
     */
    public function reorderItems(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'exists:mod_pack_items,id'],
        ]);

        // Verify all items belong to this mod pack
        $itemIds = $validated['item_ids'];
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        // Update sort_order for each item based on the new order
        foreach ($itemIds as $index => $itemId) {
            ModPackItem::where('id', $itemId)
                ->where('mod_pack_id', $modPack->id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get download info for a mod pack item (handles both CurseForge and Modrinth).
     */
    private function getItemDownloadInfo(ModPackItem $item, ModService $modService): ?array
    {
        $source = $item->source;

        // Determine source from item data if not set
        if (! $source) {
            if ($item->curseforge_mod_id && $item->curseforge_file_id) {
                $source = 'curseforge';
            } elseif ($item->modrinth_project_id && $item->modrinth_version_id) {
                $source = 'modrinth';
            } else {
                return null;
            }
        }

        $modId = null;
        $fileId = null;

        if ($source === 'curseforge') {
            if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
                return null;
            }
            $modId = $item->curseforge_mod_id;
            $fileId = $item->curseforge_file_id;
        } elseif ($source === 'modrinth') {
            if (! $item->modrinth_project_id || ! $item->modrinth_version_id) {
                return null;
            }
            $modId = $item->modrinth_project_id;
            $fileId = $item->modrinth_version_id;
        } else {
            return null;
        }

        return $modService->getFileDownloadInfo($modId, $fileId, $source);
    }

    /**
     * Get latest version/file for a mod pack item (handles both CurseForge and Modrinth).
     */
    private function getItemLatestVersion(ModPackItem $item, string $gameVersion, string $software, ModService $modService): ?array
    {
        $source = $item->source;

        // Determine source from item data if not set
        if (! $source) {
            if ($item->curseforge_mod_id) {
                $source = 'curseforge';
            } elseif ($item->modrinth_project_id) {
                $source = 'modrinth';
            } else {
                return null;
            }
        }

        $modId = null;

        if ($source === 'curseforge' && $item->curseforge_mod_id) {
            $modId = $item->curseforge_mod_id;
        } elseif ($source === 'modrinth' && $item->modrinth_project_id) {
            $modId = $item->modrinth_project_id;
        } else {
            return null;
        }

        return $modService->getLatestModFile($modId, $gameVersion, $software, $source);
    }

    /**
     * Export modpack in various formats.
     */
    public function export(Request $request, string $id, string $format)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with(['items', 'user'])
            ->findOrFail($id);

        $exportService = new ModPackExportService(new ModService);

        try {
            switch (strtolower($format)) {
                case 'curseforge':
                    $filePath = $exportService->exportAsCurseForge($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'-curseforge.zip';
                    $mimeType = 'application/zip';

                    break;
                case 'multimc':
                    $filePath = $exportService->exportAsMultiMC($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'-multimc.zip';
                    $mimeType = 'application/zip';

                    break;
                case 'modrinth':
                    $filePath = $exportService->exportAsModrinth($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'.mrpack';
                    $mimeType = 'application/x-modrinth-modpack+zip';

                    break;
                case 'text':
                    $content = $exportService->exportAsText($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'.txt';
                    $mimeType = 'text/plain';

                    // Increment downloads count
                    $modPack->increment('downloads_count');

                    return response($content, 200, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    ]);
                case 'csv':
                    $content = $exportService->exportAsCsv($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'.csv';
                    $mimeType = 'text/csv';

                    // Increment downloads count
                    $modPack->increment('downloads_count');

                    return response($content, 200, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    ]);
                default:
                    return response()->json([
                        'error' => __('messages.modpack.unsupported_export_format'),
                    ], 400);
            }

            // For ZIP-based exports
            if (file_exists($filePath)) {
                // Increment downloads count
                $modPack->increment('downloads_count');

                return response()->download($filePath, $filename, [
                    'Content-Type' => $mimeType,
                ])->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'error' => __('messages.modpack.export_failed'),
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Export failed', [
                'mod_pack_id' => $modPack->id,
                'format' => $format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => __('messages.modpack.export_failed').': '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export shared modpack in various formats.
     */
    public function exportShared(Request $request, string $token, string $format)
    {
        $modPack = ModPack::where('share_token', $token)
            ->with(['items', 'user'])
            ->firstOrFail();

        $exportService = new ModPackExportService(new ModService);

        try {
            switch (strtolower($format)) {
                case 'curseforge':
                    $filePath = $exportService->exportAsCurseForge($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'-curseforge.zip';
                    $mimeType = 'application/zip';

                    break;
                case 'multimc':
                    $filePath = $exportService->exportAsMultiMC($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'-multimc.zip';
                    $mimeType = 'application/zip';

                    break;
                case 'modrinth':
                    $filePath = $exportService->exportAsModrinth($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'.mrpack';
                    $mimeType = 'application/x-modrinth-modpack+zip';

                    break;
                case 'text':
                    $content = $exportService->exportAsText($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'.txt';
                    $mimeType = 'text/plain';

                    // Increment downloads count
                    $modPack->increment('downloads_count');

                    return response($content, 200, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    ]);
                case 'csv':
                    $content = $exportService->exportAsCsv($modPack);
                    $filename = $this->sanitizeFilename($modPack->name).'.csv';
                    $mimeType = 'text/csv';

                    // Increment downloads count
                    $modPack->increment('downloads_count');

                    return response($content, 200, [
                        'Content-Type' => $mimeType,
                        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    ]);
                default:
                    return response()->json([
                        'error' => __('messages.modpack.unsupported_export_format'),
                    ], 400);
            }

            // For ZIP-based exports
            if (file_exists($filePath)) {
                // Increment downloads count
                $modPack->increment('downloads_count');

                return response()->download($filePath, $filename, [
                    'Content-Type' => $mimeType,
                ])->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'error' => __('messages.modpack.export_failed'),
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Shared export failed', [
                'share_token' => $token,
                'format' => $format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => __('messages.modpack.export_failed').': '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sanitize filename for safe downloads.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid filename characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename); // Replace multiple underscores with single
        $filename = trim($filename, '_'); // Remove leading/trailing underscores

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'modpack';
        }

        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }

        return $filename;
    }
}
