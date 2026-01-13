<?php

namespace App\Http\Controllers;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\ModPackRun;
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

        // Get the active run (is_completed = false)
        $activeRun = $modPack->runs()
            ->where('is_completed', false)
            ->latest()
            ->first();

        $user = Auth::user();
        $isPremium = $user->isPremium();
        $monthlyRunCount = $user->getMonthlyRunCount();

        $modService = new ModService;
        $gameVersions = $modService->getGameVersions();
        $modLoaders = $modService->getModLoaders();

        return Inertia::render('ModPacks/Show', [
            'modPack' => $modPack,
            'activeRun' => $activeRun,
            'gameVersions' => $gameVersions,
            'modLoaders' => $modLoaders,
            'isPremium' => $isPremium,
            'monthlyRunCount' => $monthlyRunCount,
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
     * Create a new run for a mod pack.
     */
    public function createRun(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $user = Auth::user();

        // Check premium status and run limits for free users
        if (! $user->isPremium()) {
            $monthlyRunCount = $user->getMonthlyRunCount();
            if ($monthlyRunCount >= 10) {
                return redirect()->route('premium')->with('error', __('messages.premium.run_limit_exceeded'));
            }
        }

        // Create a new run with is_completed = false
        $run = ModPackRun::create([
            'mod_pack_id' => $modPack->id,
            'is_completed' => false,
        ]);

        // Create directory structure for the run
        $baseDir = '/shared/virtual';
        $runDir = $baseDir.'/'.$run->id;
        $modsDir = $runDir.'/mods';

        // Verify base directory exists and is writable (should be a Docker volume mount)
        // We don't try to create it since it's a volume mount - it must exist
        if (! is_dir($baseDir)) {
            throw new \RuntimeException(
                "Base directory does not exist: {$baseDir}. ".
                'Please ensure the Docker volume mount is configured correctly in docker-compose.yml '.
                'and that ./docker/virtual directory exists on the host system.'
            );
        }

        if (! is_writable($baseDir)) {
            throw new \RuntimeException(
                "Base directory is not writable: {$baseDir}. ".
                'Please check permissions on ./docker/virtual on the host system.'
            );
        }

        // Create run directory
        if (! is_dir($runDir)) {
            if (! @mkdir($runDir, 0755, true) && ! is_dir($runDir)) {
                throw new \RuntimeException("Failed to create run directory: {$runDir}");
            }
        }

        // Create mods directory
        if (! is_dir($modsDir)) {
            if (! @mkdir($modsDir, 0755, true) && ! is_dir($modsDir)) {
                throw new \RuntimeException("Failed to create mods directory: {$modsDir}");
            }
        }

        // Download mod loader from ServerJars
        $loaderDownloaded = $this->downloadModLoaderFromServerJars(
            $runDir,
            $modPack->software,
            $modPack->minecraft_version
        );

        // Initialize server JAR download status
        // For Fabric and Quilt, the installer will download the server JAR
        // For Forge and NeoForge, we need to download the vanilla server JAR for the installer
        $serverJarDownloaded = ! in_array($modPack->software, ['fabric', 'quilt', 'neoforge', 'forge']);

        if (! $loaderDownloaded) {
            \Log::warning('Failed to download mod loader from ServerJars', [
                'run_id' => $run->id,
                'software' => $modPack->software,
                'minecraft_version' => $modPack->minecraft_version,
            ]);
        } else {
            // For Fabric and Quilt, the installer will download the server JAR, so we skip the separate download
            // For NeoForge and Forge, we need to download the vanilla Minecraft server JAR for the installer
            if (in_array($modPack->software, ['neoforge', 'forge'])) {
                $serverJarDownloaded = $this->downloadVanillaServerJar(
                    $runDir,
                    $modPack->minecraft_version
                );

                if (! $serverJarDownloaded) {
                    \Log::warning('Failed to download vanilla server JAR for installer', [
                        'run_id' => $run->id,
                        'software' => $modPack->software,
                        'minecraft_version' => $modPack->minecraft_version,
                    ]);
                }
            } elseif (in_array($modPack->software, ['fabric', 'quilt'])) {
                // For Fabric and Quilt, the installer handles everything, so server JAR is already handled
                $serverJarDownloaded = true;
            }

            // Save other required files after mod loader is successfully downloaded
            $filename = $modPack->software.'.jar';

            // Write eula.txt
            $eulaWritten = file_put_contents($runDir.'/eula.txt', 'eula=true');
            if ($eulaWritten === false) {
                \Log::error('Failed to write eula.txt', [
                    'run_id' => $run->id,
                    'run_dir' => $runDir,
                ]);
            }

            // Write run.sh
            // For Fabric, we need to run the installer first, then run the generated launcher
            if ($modPack->software === 'fabric' && file_exists($runDir.'/fabric-installer-info.txt')) {
                $installerInfo = json_decode(file_get_contents($runDir.'/fabric-installer-info.txt'), true);
                $runShContent = "#!/bin/sh\n";
                $runShContent .= "# Run Fabric installer to generate server launcher\n";
                $runShContent .= "java -jar fabric-installer.jar server -mcversion {$installerInfo['minecraft_version']} -loader {$installerInfo['loader_version']} -downloadMinecraft > logs.txt 2>&1\n";
                $runShContent .= "# Run the generated Fabric server launcher\n";
                $runShContent .= "java -jar fabric-server-launch.jar >> logs.txt 2>&1\n";
            } elseif ($modPack->software === 'quilt' && file_exists($runDir.'/quilt-installer-info.txt')) {
                // For Quilt, we need to run the installer first, then run the generated launcher
                $installerInfo = json_decode(file_get_contents($runDir.'/quilt-installer-info.txt'), true);
                $runShContent = "#!/bin/sh\n";
                $runShContent .= "# Run Quilt installer to generate server launcher\n";
                $runShContent .= "java -jar quilt-installer.jar install server {$installerInfo['minecraft_version']} {$installerInfo['loader_version']} --install-dir=. --download-server --create-scripts > logs.txt 2>&1\n";
                $runShContent .= "# Check installer exit code\n";
                $runShContent .= "INSTALLER_EXIT=\$?\n";
                $runShContent .= "if [ \$INSTALLER_EXIT -ne 0 ]; then\n";
                $runShContent .= "  echo 'Quilt installer failed with exit code ' \$INSTALLER_EXIT '.' >> logs.txt 2>&1\n";
                $runShContent .= "  exit 1\n";
                $runShContent .= "fi\n";
                $runShContent .= "# Run the generated Quilt server launcher\n";
                $runShContent .= "# Quilt installer should generate quilt-server-launch.jar or set up server.jar with the loader\n";
                $runShContent .= "if [ -f quilt-server-launch.jar ]; then\n";
                $runShContent .= "  java -jar quilt-server-launch.jar nogui >> logs.txt 2>&1\n";
                $runShContent .= "elif [ -f server.jar ]; then\n";
                $runShContent .= "  # Quilt installer should have set up server.jar with the loader\n";
                $runShContent .= "  java -jar server.jar nogui >> logs.txt 2>&1\n";
                $runShContent .= "else\n";
                $runShContent .= "  echo 'Error: No server launcher found after Quilt installation.' >> logs.txt 2>&1\n";
                $runShContent .= "  echo 'Installer exit code: ' \$INSTALLER_EXIT >> logs.txt 2>&1\n";
                $runShContent .= "  echo 'Files in directory:' >> logs.txt 2>&1\n";
                $runShContent .= "  ls -la >> logs.txt 2>&1\n";
                $runShContent .= "  exit 1\n";
                $runShContent .= "fi\n";
            } elseif ($modPack->software === 'neoforge' && file_exists($runDir.'/neoforge-installer-info.txt')) {
                // For NeoForge, run the installer with --installServer flag
                $installerInfo = json_decode(file_get_contents($runDir.'/neoforge-installer-info.txt'), true);
                $runShContent = "#!/bin/sh\n";
                $runShContent .= "# Check if vanilla server JAR exists (required for NeoForge installer)\n";
                $runShContent .= "if [ ! -f server.jar ]; then\n";
                $runShContent .= "  echo 'Error: server.jar not found. NeoForge installer requires vanilla server JAR.' > logs.txt 2>&1\n";
                $runShContent .= "  exit 1\n";
                $runShContent .= "fi\n";
                $runShContent .= "# Run NeoForge installer to generate server files\n";
                $runShContent .= "java -jar neoforge-installer.jar --installServer > logs.txt 2>&1\n";
                $runShContent .= "INSTALLER_EXIT=\$?\n";
                $runShContent .= "if [ \$INSTALLER_EXIT -ne 0 ]; then\n";
                $runShContent .= "  echo 'NeoForge installer failed with exit code ' \$INSTALLER_EXIT '.' >> logs.txt 2>&1\n";
                $runShContent .= "  echo 'Files in directory:' >> logs.txt 2>&1\n";
                $runShContent .= "  ls -la >> logs.txt 2>&1\n";
                $runShContent .= "  exit 1\n";
                $runShContent .= "fi\n";
                $runShContent .= "# Run the generated NeoForge server (run.sh is generated by installer)\n";
                $runShContent .= "if [ -f run.sh ]; then\n";
                $runShContent .= "  chmod +x run.sh\n";
                $runShContent .= "  ./run.sh nogui >> logs.txt 2>&1\n";
                $runShContent .= "elif [ -f server.jar ]; then\n";
                $runShContent .= "  # Fallback: try to run server jar directly if run.sh not generated\n";
                $runShContent .= "  java -jar server.jar nogui >> logs.txt 2>&1\n";
                $runShContent .= "else\n";
                $runShContent .= "  echo 'Error: No server launcher found after NeoForge installation.' >> logs.txt 2>&1\n";
                $runShContent .= "  echo 'Installer exit code: ' \$INSTALLER_EXIT >> logs.txt 2>&1\n";
                $runShContent .= "  echo 'Files in directory:' >> logs.txt 2>&1\n";
                $runShContent .= "  ls -la >> logs.txt 2>&1\n";
                $runShContent .= "  exit 1\n";
                $runShContent .= "fi\n";
            } elseif ($modPack->software === 'forge' && file_exists($runDir.'/forge-installer-info.txt')) {
                // For Forge, run the installer with --installServer flag
                $installerInfo = json_decode(file_get_contents($runDir.'/forge-installer-info.txt'), true);
                $runShContent = "#!/bin/sh\n";
                $runShContent .= "# Run Forge installer to generate server files\n";
                $runShContent .= "java -jar forge-installer.jar --installServer > logs.txt 2>&1\n";
                $runShContent .= "# Run the generated Forge server (run.sh is generated by installer)\n";
                $runShContent .= "if [ -f run.sh ]; then\n";
                $runShContent .= "  chmod +x run.sh\n";
                $runShContent .= "  ./run.sh >> logs.txt 2>&1\n";
                $runShContent .= "else\n";
                $runShContent .= "  # Fallback: try to run server jar directly if run.sh not generated\n";
                $runShContent .= "  java -jar server.jar >> logs.txt 2>&1\n";
                $runShContent .= "fi\n";
            } else {
                $runShContent = "#!/bin/sh\n";
                $runShContent .= "java -jar {$filename} > logs.txt 2>&1\n";
            }

            $runShWritten = file_put_contents($runDir.'/run.sh', $runShContent);
            if ($runShWritten === false) {
                \Log::error('Failed to write run.sh', [
                    'run_id' => $run->id,
                    'run_dir' => $runDir,
                ]);
            } else {
                // Make run.sh executable
                chmod($runDir.'/run.sh', 0755);
            }

            \Log::info('Mod loader files written successfully', [
                'run_id' => $run->id,
                'eula_written' => $eulaWritten !== false,
                'run_sh_written' => $runShWritten !== false,
                'server_jar_downloaded' => $serverJarDownloaded,
            ]);
        }

        // Download all mods from the modpack
        $modService = new ModService;
        $downloadedCount = 0;
        $failedCount = 0;

        foreach ($modPack->items as $item) {
            $downloadInfo = $this->getItemDownloadInfo($item, $modService);

            if ($downloadInfo && isset($downloadInfo['url'])) {
                try {
                    $response = Http::timeout(60)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                        ])
                        ->get($downloadInfo['url']);

                    if ($response->successful()) {
                        $filename = $downloadInfo['filename'] ?? basename(parse_url($downloadInfo['url'], PHP_URL_PATH));
                        if (! $filename || ! preg_match('/\.jar$/', $filename)) {
                            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $item->mod_name).'.jar';
                        }

                        $filePath = $modsDir.'/'.$filename;
                        file_put_contents($filePath, $response->body());
                        $downloadedCount++;
                    } else {
                        \Log::warning('Failed to download mod file for run', [
                            'run_id' => $run->id,
                            'item_id' => $item->id,
                            'mod_name' => $item->mod_name,
                            'url' => $downloadInfo['url'],
                            'status' => $response->status(),
                        ]);
                        $failedCount++;
                    }
                } catch (\Exception $e) {
                    \Log::error('Error downloading mod file for run', [
                        'run_id' => $run->id,
                        'item_id' => $item->id,
                        'mod_name' => $item->mod_name,
                        'url' => $downloadInfo['url'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            } else {
                \Log::warning('No download info available for mod item', [
                    'run_id' => $run->id,
                    'item_id' => $item->id,
                    'mod_name' => $item->mod_name,
                ]);
                $failedCount++;
            }
        }

        // Write runner.pick file AFTER all files are ready (mods and mod loader)
        // This signals the runner.sh script that the run is ready to execute
        // Only write runner.pick if mod loader was successfully downloaded
        // For Fabric/Quilt/NeoForge, also require server JAR to be downloaded
        $runnerPickWritten = false;
        $canRun = $loaderDownloaded && $serverJarDownloaded;

        if ($canRun) {
            $runnerPickWritten = file_put_contents($runDir.'/runner.pick', '1');
            if ($runnerPickWritten === false) {
                \Log::error('Failed to write runner.pick', [
                    'run_id' => $run->id,
                    'run_dir' => $runDir,
                ]);
            } else {
                \Log::info('runner.pick file created successfully', [
                    'run_id' => $run->id,
                ]);
            }
        } else {
            \Log::warning('Skipping runner.pick creation - required downloads failed', [
                'run_id' => $run->id,
                'loader_downloaded' => $loaderDownloaded,
                'server_jar_downloaded' => $serverJarDownloaded ?? null,
            ]);
        }

        \Log::info('Run created with mod downloads', [
            'run_id' => $run->id,
            'mod_pack_id' => $modPack->id,
            'downloaded_count' => $downloadedCount,
            'failed_count' => $failedCount,
            'loader_downloaded' => $loaderDownloaded,
            'runner_pick_written' => $runnerPickWritten !== false,
        ]);

        // Return JSON for AJAX requests, redirect for regular requests
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $run,
                'downloaded_count' => $downloadedCount,
                'failed_count' => $failedCount,
            ]);
        }

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Stop (complete) a run for a mod pack.
     */
    public function stopRun(Request $request, string $id, string $runId)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);
        $run = ModPackRun::where('mod_pack_id', $modPack->id)
            ->findOrFail($runId);

        $run->update([
            'is_completed' => true,
        ]);

        return response()->json([
            'message' => 'Run stopped successfully',
            'data' => $run,
        ]);
    }

    /**
     * Get run history for a mod pack.
     */
    public function getRunHistory(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $runs = ModPackRun::where('mod_pack_id', $modPack->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $runs,
        ]);
    }

    /**
     * Get logs for a specific run.
     */
    public function getRunLogs(Request $request, string $id, string $runId)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);
        $run = ModPackRun::where('mod_pack_id', $modPack->id)
            ->findOrFail($runId);

        $logsPath = '/shared/virtual/'.$runId.'/logs.txt';

        if (! file_exists($logsPath)) {
            return response()->json([
                'data' => '',
            ]);
        }

        $logs = file_get_contents($logsPath);

        return response()->json([
            'data' => $logs ?: '',
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
     * Download mod loader from ServerJars.
     */
    private function downloadModLoaderFromServerJars(string $runDir, string $software, string $minecraftVersion): bool
    {
        \Log::info('Attempting to download mod loader', [
            'run_dir' => $runDir,
            'software' => $software,
            'minecraft_version' => $minecraftVersion,
        ]);

        // For Fabric and Quilt, always use the installer approach
        if ($software === 'fabric') {
            return $this->downloadFabricInstaller($runDir, $minecraftVersion);
        }

        if ($software === 'quilt') {
            return $this->downloadQuiltInstaller($runDir, $minecraftVersion);
        }

        // Map software types to ServerJars API types
        $serverJarsTypeMap = [
            'forge' => 'modded/forge',
            'neoforge' => 'modded/neoforge',
        ];

        if (! isset($serverJarsTypeMap[$software])) {
            \Log::warning('Unsupported software type for ServerJars download', [
                'software' => $software,
            ]);

            return false;
        }

        $type = $serverJarsTypeMap[$software];

        try {
            // Try to get the latest build number for the specified version
            // ServerJars API format: /api/fetchLatest/{type}/{version}
            $latestUrl = "https://serverjars.com/api/fetchLatest/{$type}/{$minecraftVersion}";

            \Log::debug('Fetching latest build from ServerJars', [
                'url' => $latestUrl,
            ]);

            $latestResponse = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($latestUrl);

            $build = null;
            $downloadUrl = null;

            if ($latestResponse->successful()) {
                $latestData = $latestResponse->json();
                \Log::debug('ServerJars latest build response', [
                    'response' => $latestData,
                ]);

                // Try different possible response structures
                $build = $latestData['response']['build']
                    ?? $latestData['response']['version']
                    ?? $latestData['build']
                    ?? $latestData['version']
                    ?? $latestData['latest']
                    ?? null;

                if ($build) {
                    \Log::info('Found build number from ServerJars', [
                        'build' => $build,
                    ]);
                    // ServerJars API format: /api/fetchJar/{type}/{version}/{build}
                    $downloadUrl = "https://serverjars.com/api/fetchJar/{$type}/{$minecraftVersion}/{$build}";
                } else {
                    \Log::warning('No build number found in ServerJars response, trying direct download', [
                        'response' => $latestData,
                    ]);
                }
            } else {
                \Log::warning('Failed to get latest build from ServerJars, trying direct download', [
                    'url' => $latestUrl,
                    'status' => $latestResponse->status(),
                    'body' => $latestResponse->body(),
                ]);
            }

            // Fallback: try direct download without build number
            // Some ServerJars endpoints might support: /api/fetchJar/{type}/{version}/latest
            if (! $downloadUrl) {
                $downloadUrl = "https://serverjars.com/api/fetchJar/{$type}/{$minecraftVersion}/latest";
                \Log::info('Trying direct download with latest', [
                    'url' => $downloadUrl,
                ]);
            }

            \Log::debug('Downloading jar from ServerJars', [
                'url' => $downloadUrl,
            ]);

            $downloadResponse = Http::timeout(120) // Longer timeout for large files
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                    'Accept' => 'application/java-archive, application/octet-stream, */*',
                ])
                ->withoutRedirecting() // We'll handle redirects manually
                ->get($downloadUrl);

            // Check if we got a redirect
            if ($downloadResponse->status() >= 300 && $downloadResponse->status() < 400) {
                $redirectUrl = $downloadResponse->header('Location');
                if ($redirectUrl) {
                    \Log::info('Following redirect from ServerJars', [
                        'original_url' => $downloadUrl,
                        'redirect_url' => $redirectUrl,
                    ]);
                    $downloadUrl = $redirectUrl;
                    $downloadResponse = Http::timeout(120)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                            'Accept' => 'application/java-archive, application/octet-stream, */*',
                        ])
                        ->get($downloadUrl);
                }
            }

            if (! $downloadResponse->successful()) {
                \Log::warning('Failed to download mod loader from ServerJars', [
                    'url' => $downloadUrl,
                    'status' => $downloadResponse->status(),
                    'body_preview' => substr($downloadResponse->body(), 0, 200),
                ]);

                // For NeoForge and Forge, try installer fallback if ServerJars download fails
                if ($software === 'neoforge') {
                    \Log::info('ServerJars download failed for NeoForge, falling back to installer method');

                    return $this->downloadNeoForgeInstaller($runDir, $minecraftVersion, $latestData ?? null);
                }

                if ($software === 'forge') {
                    \Log::info('ServerJars download failed for Forge, falling back to installer method');

                    return $this->downloadForgeInstaller($runDir, $minecraftVersion, $latestData ?? null);
                }

                return false;
            }

            $responseBody = $downloadResponse->body();

            // Check if we got HTML instead of a JAR file (ServerJars sometimes returns HTML redirect pages)
            if (str_starts_with(trim($responseBody), '<html') || str_starts_with(trim($responseBody), '<!DOCTYPE')) {
                \Log::warning('ServerJars returned HTML instead of JAR file, trying alternative method', [
                    'url' => $downloadUrl,
                    'body_preview' => substr($responseBody, 0, 500),
                ]);

                // Try using the direct download URL from the official sources
                // For Fabric, we need to use the Fabric installer to create a proper server launcher
                if ($software === 'fabric') {
                    // Use Fabric's installer API to get the installer JAR
                    // The installer will be run by the Docker container to generate the server launcher
                    $installerApiUrl = 'https://meta.fabricmc.net/v2/versions/installer';
                    $installerResponse = Http::timeout(30)->get($installerApiUrl);

                    if ($installerResponse->successful()) {
                        $installerData = $installerResponse->json();
                        if (! empty($installerData) && isset($installerData[0]['version'])) {
                            // Get the latest installer version
                            $installerVersion = $installerData[0]['version'];
                            // Fabric installer download from Maven Central
                            $installerUrl = "https://maven.fabricmc.net/net/fabricmc/fabric-installer/{$installerVersion}/fabric-installer-{$installerVersion}.jar";

                            \Log::info('Downloading Fabric installer', [
                                'url' => $installerUrl,
                                'installer_version' => $installerVersion,
                            ]);

                            $installerDownloadResponse = Http::timeout(120)
                                ->withHeaders([
                                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                                ])
                                ->get($installerUrl);

                            if ($installerDownloadResponse->successful()) {
                                $installerJar = $installerDownloadResponse->body();

                                // Validate installer JAR
                                if (strlen($installerJar) < 4 || substr($installerJar, 0, 2) !== 'PK') {
                                    \Log::error('Downloaded Fabric installer does not appear to be a valid JAR file', [
                                        'file_size' => strlen($installerJar),
                                        'first_bytes' => bin2hex(substr($installerJar, 0, 10)),
                                    ]);

                                    return false;
                                }

                                // Save the installer JAR
                                $installerPath = $runDir.'/fabric-installer.jar';
                                $installerWritten = file_put_contents($installerPath, $installerJar);

                                if ($installerWritten === false) {
                                    \Log::error('Failed to write Fabric installer to disk', [
                                        'file_path' => $installerPath,
                                    ]);

                                    return false;
                                }

                                // Now we need to run the installer to generate the server launcher
                                // We'll modify run.sh to run the installer first
                                // The installer command: java -jar fabric-installer.jar server -mcversion {version} -loader {loader_version} -downloadMinecraft

                                // Get the loader version
                                $loaderApiUrl = "https://meta.fabricmc.net/v2/versions/loader/{$minecraftVersion}";
                                $loaderResponse = Http::timeout(30)->get($loaderApiUrl);

                                if ($loaderResponse->successful()) {
                                    $loaderData = $loaderResponse->json();
                                    if (! empty($loaderData) && isset($loaderData[0]['loader']['version'])) {
                                        $loaderVersion = $loaderData[0]['loader']['version'];

                                        // We'll need to run the installer, but we can't do that from PHP
                                        // Instead, we'll save the installer and modify run.sh to run it first
                                        // For now, return true and we'll handle the installer execution in run.sh
                                        \Log::info('Fabric installer downloaded, will be executed by run.sh', [
                                            'installer_path' => $installerPath,
                                            'loader_version' => $loaderVersion,
                                            'minecraft_version' => $minecraftVersion,
                                        ]);

                                        // Store installer info for run.sh
                                        file_put_contents($runDir.'/fabric-installer-info.txt', json_encode([
                                            'installer_version' => $installerVersion,
                                            'loader_version' => $loaderVersion,
                                            'minecraft_version' => $minecraftVersion,
                                        ]));

                                        // Return true to indicate we have the installer
                                        // The actual server launcher will be generated by run.sh
                                        return true;
                                    }
                                }

                                \Log::error('Failed to get Fabric loader version', [
                                    'url' => $loaderApiUrl,
                                ]);

                                return false;
                            } else {
                                \Log::error('Failed to download Fabric installer', [
                                    'url' => $installerUrl,
                                    'status' => $installerDownloadResponse->status(),
                                ]);

                                return false;
                            }
                        } else {
                            \Log::error('Invalid response from Fabric installer API', [
                                'response' => $installerData,
                            ]);

                            return false;
                        }
                    } else {
                        \Log::error('Failed to fetch Fabric installer info', [
                            'url' => $installerApiUrl,
                            'status' => $installerResponse->status(),
                        ]);

                        return false;
                    }
                } elseif ($software === 'neoforge') {
                    // For NeoForge, try to use the installer approach
                    \Log::info('ServerJars returned HTML for NeoForge, falling back to installer method');

                    return $this->downloadNeoForgeInstaller($runDir, $minecraftVersion, $latestData ?? null);
                } elseif ($software === 'forge') {
                    // For Forge, try to use the installer approach
                    \Log::info('ServerJars returned HTML for Forge, falling back to installer method');

                    return $this->downloadForgeInstaller($runDir, $minecraftVersion, $latestData ?? null);
                } else {
                    // For other loaders, we might need to handle differently
                    \Log::error('ServerJars returned HTML for unsupported loader, cannot proceed', [
                        'software' => $software,
                    ]);

                    return false;
                }
            }

            // Validate that we actually got a JAR file (JAR files are ZIP files, check for ZIP magic bytes)
            if (strlen($responseBody) < 4 || substr($responseBody, 0, 2) !== 'PK') {
                \Log::error('Downloaded file does not appear to be a valid JAR file (missing ZIP magic bytes)', [
                    'file_size' => strlen($responseBody),
                    'first_bytes' => bin2hex(substr($responseBody, 0, 10)),
                ]);

                // For NeoForge and Forge, try installer fallback if downloaded file is invalid
                if ($software === 'neoforge') {
                    \Log::info('ServerJars download returned invalid file for NeoForge, falling back to installer method');

                    return $this->downloadNeoForgeInstaller($runDir, $minecraftVersion, $latestData ?? null);
                }

                if ($software === 'forge') {
                    \Log::info('ServerJars download returned invalid file for Forge, falling back to installer method');

                    return $this->downloadForgeInstaller($runDir, $minecraftVersion, $latestData ?? null);
                }

                return false;
            }

            // Determine the filename based on software type
            $filename = $software.'.jar';
            $filePath = $runDir.'/'.$filename;

            // Save the jar file
            $bytesWritten = file_put_contents($filePath, $responseBody);

            if ($bytesWritten === false) {
                \Log::error('Failed to write mod loader file to disk', [
                    'file_path' => $filePath,
                    'run_dir' => $runDir,
                    'directory_exists' => is_dir($runDir),
                    'directory_writable' => is_writable($runDir),
                ]);

                return false;
            }

            \Log::info('Successfully downloaded mod loader from ServerJars', [
                'software' => $software,
                'minecraft_version' => $minecraftVersion,
                'build' => $build,
                'file_path' => $filePath,
                'file_size' => $bytesWritten,
                'file_exists' => file_exists($filePath),
            ]);

            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Connection error downloading mod loader from ServerJars', [
                'software' => $software,
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Exception $e) {
            \Log::error('Error downloading mod loader from ServerJars', [
                'software' => $software,
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Download Fabric installer and set it up for server generation.
     */
    private function downloadFabricInstaller(string $runDir, string $minecraftVersion): bool
    {
        \Log::info('Downloading Fabric installer', [
            'run_dir' => $runDir,
            'minecraft_version' => $minecraftVersion,
        ]);

        try {
            // Get the latest installer version
            $installerApiUrl = 'https://meta.fabricmc.net/v2/versions/installer';
            $installerResponse = Http::timeout(30)->get($installerApiUrl);

            if (! $installerResponse->successful()) {
                \Log::error('Failed to fetch Fabric installer version', [
                    'url' => $installerApiUrl,
                    'status' => $installerResponse->status(),
                ]);

                return false;
            }

            $installerData = $installerResponse->json();
            if (empty($installerData) || ! isset($installerData[0]['version'])) {
                \Log::error('Invalid response from Fabric installer API', [
                    'response' => $installerData,
                ]);

                return false;
            }

            $installerVersion = $installerData[0]['version'];
            $installerUrl = "https://maven.fabricmc.net/net/fabricmc/fabric-installer/{$installerVersion}/fabric-installer-{$installerVersion}.jar";

            \Log::info('Downloading Fabric installer JAR', [
                'url' => $installerUrl,
                'installer_version' => $installerVersion,
            ]);

            $installerDownloadResponse = Http::timeout(120)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($installerUrl);

            if (! $installerDownloadResponse->successful()) {
                \Log::error('Failed to download Fabric installer', [
                    'url' => $installerUrl,
                    'status' => $installerDownloadResponse->status(),
                ]);

                return false;
            }

            $installerJar = $installerDownloadResponse->body();

            // Validate installer JAR
            if (strlen($installerJar) < 4 || substr($installerJar, 0, 2) !== 'PK') {
                \Log::error('Downloaded Fabric installer does not appear to be a valid JAR file', [
                    'file_size' => strlen($installerJar),
                    'first_bytes' => bin2hex(substr($installerJar, 0, 10)),
                ]);

                return false;
            }

            // Save the installer JAR
            $installerPath = $runDir.'/fabric-installer.jar';
            $installerWritten = file_put_contents($installerPath, $installerJar);

            if ($installerWritten === false) {
                \Log::error('Failed to write Fabric installer to disk', [
                    'file_path' => $installerPath,
                ]);

                return false;
            }

            // Get the loader version
            $loaderApiUrl = "https://meta.fabricmc.net/v2/versions/loader/{$minecraftVersion}";
            $loaderResponse = Http::timeout(30)->get($loaderApiUrl);

            if (! $loaderResponse->successful()) {
                \Log::error('Failed to get Fabric loader version', [
                    'url' => $loaderApiUrl,
                    'status' => $loaderResponse->status(),
                ]);

                return false;
            }

            $loaderData = $loaderResponse->json();
            if (empty($loaderData) || ! isset($loaderData[0]['loader']['version'])) {
                \Log::error('Invalid response from Fabric loader API', [
                    'response' => $loaderData,
                ]);

                return false;
            }

            $loaderVersion = $loaderData[0]['loader']['version'];

            // Store installer info for run.sh
            $installerInfo = [
                'installer_version' => $installerVersion,
                'loader_version' => $loaderVersion,
                'minecraft_version' => $minecraftVersion,
            ];

            $infoWritten = file_put_contents($runDir.'/fabric-installer-info.txt', json_encode($installerInfo));
            if ($infoWritten === false) {
                \Log::error('Failed to write Fabric installer info', [
                    'file_path' => $runDir.'/fabric-installer-info.txt',
                ]);

                return false;
            }

            \Log::info('Fabric installer downloaded successfully', [
                'installer_path' => $installerPath,
                'loader_version' => $loaderVersion,
                'minecraft_version' => $minecraftVersion,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Error downloading Fabric installer', [
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Download Quilt installer and set it up for server generation.
     */
    private function downloadQuiltInstaller(string $runDir, string $minecraftVersion): bool
    {
        \Log::info('Downloading Quilt installer', [
            'run_dir' => $runDir,
            'minecraft_version' => $minecraftVersion,
        ]);

        try {
            // Get the latest installer version
            $installerApiUrl = 'https://meta.quiltmc.org/v3/versions/installer';
            $installerResponse = Http::timeout(30)->get($installerApiUrl);

            if (! $installerResponse->successful()) {
                \Log::error('Failed to fetch Quilt installer version', [
                    'url' => $installerApiUrl,
                    'status' => $installerResponse->status(),
                ]);

                return false;
            }

            $installerData = $installerResponse->json();
            if (empty($installerData) || ! isset($installerData[0]['version'])) {
                \Log::error('Invalid response from Quilt installer API', [
                    'response' => $installerData,
                ]);

                return false;
            }

            $installerVersion = $installerData[0]['version'];
            $installerUrl = "https://maven.quiltmc.org/repository/release/org/quiltmc/quilt-installer/{$installerVersion}/quilt-installer-{$installerVersion}.jar";

            \Log::info('Downloading Quilt installer JAR', [
                'url' => $installerUrl,
                'installer_version' => $installerVersion,
            ]);

            $installerDownloadResponse = Http::timeout(120)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($installerUrl);

            if (! $installerDownloadResponse->successful()) {
                \Log::error('Failed to download Quilt installer', [
                    'url' => $installerUrl,
                    'status' => $installerDownloadResponse->status(),
                ]);

                return false;
            }

            $installerJar = $installerDownloadResponse->body();

            // Validate installer JAR
            if (strlen($installerJar) < 4 || substr($installerJar, 0, 2) !== 'PK') {
                \Log::error('Downloaded Quilt installer does not appear to be a valid JAR file', [
                    'file_size' => strlen($installerJar),
                    'first_bytes' => bin2hex(substr($installerJar, 0, 10)),
                ]);

                return false;
            }

            // Save the installer JAR
            $installerPath = $runDir.'/quilt-installer.jar';
            $installerWritten = file_put_contents($installerPath, $installerJar);

            if ($installerWritten === false) {
                \Log::error('Failed to write Quilt installer to disk', [
                    'file_path' => $installerPath,
                ]);

                return false;
            }

            // Get the loader version
            $loaderApiUrl = "https://meta.quiltmc.org/v3/versions/loader/{$minecraftVersion}";
            $loaderResponse = Http::timeout(30)->get($loaderApiUrl);

            if (! $loaderResponse->successful()) {
                \Log::error('Failed to get Quilt loader version', [
                    'url' => $loaderApiUrl,
                    'status' => $loaderResponse->status(),
                ]);

                return false;
            }

            $loaderData = $loaderResponse->json();
            if (empty($loaderData) || ! isset($loaderData[0]['loader']['version'])) {
                \Log::error('Invalid response from Quilt loader API', [
                    'response' => $loaderData,
                ]);

                return false;
            }

            $loaderVersion = $loaderData[0]['loader']['version'];

            // Store installer info for run.sh
            $installerInfo = [
                'installer_version' => $installerVersion,
                'loader_version' => $loaderVersion,
                'minecraft_version' => $minecraftVersion,
            ];

            $infoWritten = file_put_contents($runDir.'/quilt-installer-info.txt', json_encode($installerInfo));
            if ($infoWritten === false) {
                \Log::error('Failed to write Quilt installer info', [
                    'file_path' => $runDir.'/quilt-installer-info.txt',
                ]);

                return false;
            }

            \Log::info('Quilt installer downloaded successfully', [
                'installer_path' => $installerPath,
                'loader_version' => $loaderVersion,
                'minecraft_version' => $minecraftVersion,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Error downloading Quilt installer', [
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Download NeoForge installer and set it up for server generation.
     */
    private function downloadNeoForgeInstaller(string $runDir, string $minecraftVersion, ?array $serverJarsData = null): bool
    {
        \Log::info('Downloading NeoForge installer', [
            'run_dir' => $runDir,
            'minecraft_version' => $minecraftVersion,
        ]);

        try {
            // NeoForge installer requires the vanilla server JAR to be present before it runs
            // Download it first if it doesn't exist
            $serverJarPath = $runDir.'/server.jar';
            if (! file_exists($serverJarPath)) {
                \Log::info('Vanilla server JAR not found, downloading it for NeoForge installer', [
                    'run_dir' => $runDir,
                    'minecraft_version' => $minecraftVersion,
                ]);

                $serverJarDownloaded = $this->downloadVanillaServerJar($runDir, $minecraftVersion);
                if (! $serverJarDownloaded) {
                    \Log::error('Failed to download vanilla server JAR required for NeoForge installer', [
                        'run_dir' => $runDir,
                        'minecraft_version' => $minecraftVersion,
                    ]);

                    return false;
                }
            } else {
                \Log::debug('Vanilla server JAR already exists, skipping download', [
                    'server_jar_path' => $serverJarPath,
                ]);
            }
            // Try to get NeoForge version from ServerJars data if available
            $neoforgeVersion = null;
            if ($serverJarsData) {
                // ServerJars might include version info in the response
                $neoforgeVersion = $serverJarsData['response']['version']
                    ?? $serverJarsData['version']
                    ?? $serverJarsData['response']['neoforge_version']
                    ?? null;
            }

            // If we don't have version from ServerJars, try to get it from Modrinth API first
            // NeoForge project slug on Modrinth is "neoforge"
            if (! $neoforgeVersion) {
                try {
                    \Log::debug('Attempting to get NeoForge version from Modrinth API', [
                        'minecraft_version' => $minecraftVersion,
                        'project_slug' => 'neoforge',
                    ]);

                    $modrinthService = new \App\Services\ModrinthService;
                    $neoforgeVersions = $modrinthService->getProjectVersions('neoforge', $minecraftVersion, 'neoforge');

                    \Log::debug('Modrinth API response for NeoForge', [
                        'versions_count' => count($neoforgeVersions),
                        'first_version' => $neoforgeVersions[0] ?? null,
                    ]);

                    if (! empty($neoforgeVersions) && isset($neoforgeVersions[0]['version_number'])) {
                        $neoforgeVersion = $neoforgeVersions[0]['version_number'];
                        \Log::info('Found NeoForge version from Modrinth', [
                            'version' => $neoforgeVersion,
                            'version_id' => $neoforgeVersions[0]['id'] ?? null,
                        ]);
                    } else {
                        \Log::debug('No NeoForge versions found in Modrinth API response', [
                            'minecraft_version' => $minecraftVersion,
                            'versions_count' => count($neoforgeVersions),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::debug('Failed to get NeoForge version from Modrinth', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If we still don't have version, try CurseForge API
            // NeoForge mod ID on CurseForge is 406902 (NeoForge project)
            if (! $neoforgeVersion) {
                try {
                    \Log::debug('Attempting to get NeoForge version from CurseForge API', [
                        'minecraft_version' => $minecraftVersion,
                        'mod_id' => 406902,
                    ]);

                    $curseForgeService = new \App\Services\CurseForgeService;
                    $neoforgeFiles = $curseForgeService->getModFiles(406902, $minecraftVersion, 'neoforge');

                    \Log::debug('CurseForge API response for NeoForge', [
                        'files_count' => count($neoforgeFiles),
                        'first_file' => $neoforgeFiles[0] ?? null,
                    ]);

                    if (! empty($neoforgeFiles) && isset($neoforgeFiles[0]['displayName'])) {
                        // Extract version from display name (e.g., "NeoForge 20.1.0" -> "20.1.0")
                        $displayName = $neoforgeFiles[0]['displayName'];
                        \Log::debug('Extracting version from CurseForge display name', [
                            'display_name' => $displayName,
                        ]);

                        if (preg_match('/(\d+\.\d+\.\d+(?:\.\d+)?)/', $displayName, $matches)) {
                            $neoforgeVersion = $matches[1];
                            \Log::info('Found NeoForge version from CurseForge', [
                                'version' => $neoforgeVersion,
                                'display_name' => $displayName,
                            ]);
                        } else {
                            \Log::warning('Could not extract version from CurseForge display name', [
                                'display_name' => $displayName,
                            ]);
                        }
                    } else {
                        \Log::warning('No NeoForge files found in CurseForge API response', [
                            'minecraft_version' => $minecraftVersion,
                            'files_count' => count($neoforgeFiles),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to get NeoForge version from CurseForge', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // If we still don't have a version, try to query Maven metadata to find available versions
            if (! $neoforgeVersion) {
                try {
                    \Log::debug('Attempting to get NeoForge version from Maven metadata', [
                        'minecraft_version' => $minecraftVersion,
                    ]);

                    // Query Maven metadata to get available versions
                    $metadataUrl = 'https://maven.neoforged.net/releases/net/neoforged/neoforge/maven-metadata.xml';
                    $metadataResponse = Http::timeout(30)->get($metadataUrl);

                    if ($metadataResponse->successful()) {
                        $metadataXml = $metadataResponse->body();
                        // Parse XML to extract version numbers
                        if (preg_match_all('/<version>([^<]+)<\/version>/', $metadataXml, $matches)) {
                            $availableVersions = $matches[1];
                            \Log::debug('Found available NeoForge versions from Maven', [
                                'versions_count' => count($availableVersions),
                                'sample_versions' => array_slice($availableVersions, 0, 10),
                            ]);

                            // Extract major version from Minecraft version (e.g., 1.21 -> 21)
                            $mcMajor = null;
                            if (preg_match('/^1\.(\d+)/', $minecraftVersion, $mcMatches)) {
                                $mcMajor = $mcMatches[1];
                            }

                            // Find versions that match the Minecraft version pattern
                            // NeoForge versions typically start with the major version (e.g., 21.x.x for MC 1.21)
                            foreach ($availableVersions as $version) {
                                // Check if version starts with the major version number
                                if ($mcMajor && preg_match('/^'.$mcMajor.'\./', $version)) {
                                    $neoforgeVersion = $version;
                                    \Log::info('Found NeoForge version from Maven metadata', [
                                        'version' => $neoforgeVersion,
                                        'minecraft_version' => $minecraftVersion,
                                    ]);
                                    break;
                                }
                            }

                            // If no exact match, try to find the latest version that might work
                            if (! $neoforgeVersion && ! empty($availableVersions)) {
                                // Sort versions and try the latest ones
                                usort($availableVersions, 'version_compare');
                                $availableVersions = array_reverse($availableVersions);

                                // Try the latest 5 versions
                                foreach (array_slice($availableVersions, 0, 5) as $version) {
                                    $installerUrl = "https://maven.neoforged.net/releases/net/neoforged/neoforge/{$version}/neoforge-{$version}-installer.jar";
                                    try {
                                        $testResponse = Http::timeout(10)->head($installerUrl);
                                        if ($testResponse->successful()) {
                                            $neoforgeVersion = $version;
                                            \Log::info('Found NeoForge version from Maven (latest available)', [
                                                'version' => $neoforgeVersion,
                                                'minecraft_version' => $minecraftVersion,
                                            ]);
                                            break;
                                        }
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug('Failed to get NeoForge version from Maven metadata', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If we still don't have a version, try to construct it from Minecraft version
            // NeoForge versions typically follow the pattern: {major}.{minor}.{patch}
            // For Minecraft 1.20.x, NeoForge versions are typically 20.x.x
            // For Minecraft 1.21, NeoForge versions are typically 21.0.x
            if (! $neoforgeVersion) {
                $major = null;
                $minor = null;

                // Try to match versions with patch number (e.g., "1.20.1")
                if (preg_match('/^1\.(\d+)\.(\d+)/', $minecraftVersion, $matches)) {
                    $major = $matches[1];
                    $minor = $matches[2];
                }
                // Try to match versions without patch number (e.g., "1.21")
                elseif (preg_match('/^1\.(\d+)$/', $minecraftVersion, $matches)) {
                    $major = $matches[1];
                    $minor = '0'; // Default to 0 for minor when patch is missing
                }

                if ($major !== null && $minor !== null) {
                    // Try common NeoForge version patterns
                    // For versions like 1.21, try 21.0.0, 21.0.1, etc.
                    // For versions like 1.20.1, try 20.1.0, 20.1.1, etc.
                    $possibleVersions = [];

                    // First try versions matching the minor number
                    for ($patch = 0; $patch <= 10; $patch++) {
                        $possibleVersions[] = "{$major}.{$minor}.{$patch}";
                    }

                    // Also try with minor as 0 (for versions like 1.21 -> 21.0.x)
                    if ($minor !== '0') {
                        for ($patch = 0; $patch <= 10; $patch++) {
                            $possibleVersions[] = "{$major}.0.{$patch}";
                        }
                    }

                    // Try beta/alpha versions (e.g., 21.0.0-beta.1)
                    for ($patch = 0; $patch <= 5; $patch++) {
                        $possibleVersions[] = "{$major}.{$minor}.{$patch}-beta.1";
                        $possibleVersions[] = "{$major}.{$minor}.{$patch}-alpha.1";
                        $possibleVersions[] = "{$major}.0.{$patch}-beta.1";
                        $possibleVersions[] = "{$major}.0.{$patch}-alpha.1";
                    }

                    // Remove duplicates
                    $possibleVersions = array_unique($possibleVersions);

                    \Log::debug('Testing NeoForge version patterns', [
                        'minecraft_version' => $minecraftVersion,
                        'possible_versions_count' => count($possibleVersions),
                        'sample_versions' => array_slice($possibleVersions, 0, 10),
                    ]);

                    // Try each possible version until we find one that exists
                    foreach ($possibleVersions as $possibleVersion) {
                        $installerUrl = "https://maven.neoforged.net/releases/net/neoforged/neoforge/{$possibleVersion}/neoforge-{$possibleVersion}-installer.jar";
                        try {
                            $testResponse = Http::timeout(10)->head($installerUrl);
                            if ($testResponse->successful()) {
                                $neoforgeVersion = $possibleVersion;
                                \Log::info('Found NeoForge version by testing Maven URLs', [
                                    'version' => $neoforgeVersion,
                                    'minecraft_version' => $minecraftVersion,
                                ]);
                                break;
                            }
                        } catch (\Exception $e) {
                            // Continue to next version
                            continue;
                        }
                    }
                }
            }

            if (! $neoforgeVersion) {
                \Log::error('Could not determine NeoForge version', [
                    'minecraft_version' => $minecraftVersion,
                ]);

                return false;
            }

            // Download NeoForge installer from Maven
            $installerUrl = "https://maven.neoforged.net/releases/net/neoforged/neoforge/{$neoforgeVersion}/neoforge-{$neoforgeVersion}-installer.jar";

            \Log::info('Downloading NeoForge installer JAR', [
                'url' => $installerUrl,
                'neoforge_version' => $neoforgeVersion,
            ]);

            $installerDownloadResponse = Http::timeout(120)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($installerUrl);

            if (! $installerDownloadResponse->successful()) {
                \Log::error('Failed to download NeoForge installer', [
                    'url' => $installerUrl,
                    'status' => $installerDownloadResponse->status(),
                ]);

                return false;
            }

            $installerJar = $installerDownloadResponse->body();

            // Validate installer JAR
            if (strlen($installerJar) < 4 || substr($installerJar, 0, 2) !== 'PK') {
                \Log::error('Downloaded NeoForge installer does not appear to be a valid JAR file', [
                    'file_size' => strlen($installerJar),
                    'first_bytes' => bin2hex(substr($installerJar, 0, 10)),
                ]);

                return false;
            }

            // Save the installer JAR
            $installerPath = $runDir.'/neoforge-installer.jar';
            $installerWritten = file_put_contents($installerPath, $installerJar);

            if ($installerWritten === false) {
                \Log::error('Failed to write NeoForge installer to disk', [
                    'file_path' => $installerPath,
                ]);

                return false;
            }

            // Store installer info for run.sh
            $installerInfo = [
                'installer_version' => $neoforgeVersion,
                'neoforge_version' => $neoforgeVersion,
                'minecraft_version' => $minecraftVersion,
            ];

            $infoWritten = file_put_contents($runDir.'/neoforge-installer-info.txt', json_encode($installerInfo));
            if ($infoWritten === false) {
                \Log::error('Failed to write NeoForge installer info', [
                    'file_path' => $runDir.'/neoforge-installer-info.txt',
                ]);

                return false;
            }

            \Log::info('NeoForge installer downloaded successfully', [
                'installer_path' => $installerPath,
                'neoforge_version' => $neoforgeVersion,
                'minecraft_version' => $minecraftVersion,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Error downloading NeoForge installer', [
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Download Forge installer and set it up for server generation.
     */
    private function downloadForgeInstaller(string $runDir, string $minecraftVersion, ?array $serverJarsData = null): bool
    {
        \Log::info('Downloading Forge installer', [
            'run_dir' => $runDir,
            'minecraft_version' => $minecraftVersion,
        ]);

        try {
            // Try to get Forge version from ServerJars data if available
            $forgeVersion = null;
            if ($serverJarsData) {
                // ServerJars might include version info in the response
                $forgeVersion = $serverJarsData['response']['version']
                    ?? $serverJarsData['version']
                    ?? $serverJarsData['response']['forge_version']
                    ?? null;
            }

            // If we don't have version from ServerJars, try to get it from CurseForge API
            // Forge mod ID on CurseForge is 306612 (Minecraft Forge)
            if (! $forgeVersion) {
                try {
                    \Log::debug('Attempting to get Forge version from CurseForge API', [
                        'minecraft_version' => $minecraftVersion,
                        'mod_id' => 306612,
                    ]);

                    $curseForgeService = new \App\Services\CurseForgeService;

                    // Try with the exact version first
                    $forgeFiles = $curseForgeService->getModFiles(306612, $minecraftVersion, 'forge');

                    // If no files found, try without version filtering to get all Forge files
                    // Then we'll search for files matching our version pattern
                    if (empty($forgeFiles)) {
                        \Log::debug('No Forge files found with exact version, trying without version filter', [
                            'minecraft_version' => $minecraftVersion,
                        ]);

                        // Try direct API call to bypass caching issues
                        try {
                            $curseForgeApiKey = config('services.curseforge.api_key');
                            if ($curseForgeApiKey) {
                                $directResponse = Http::withHeaders([
                                    'x-api-key' => $curseForgeApiKey,
                                ])->get('https://api.curseforge.com/v1/mods/306612/files', [
                                    'modLoaderType' => 1, // Forge
                                ]);

                                if ($directResponse->successful()) {
                                    $directFiles = $directResponse->json('data', []);
                                    \Log::debug('Direct CurseForge API call returned files', [
                                        'files_count' => count($directFiles),
                                    ]);

                                    // Filter files that match our Minecraft version pattern
                                    $normalizedRequested = preg_replace('/\.0+$/', '', $minecraftVersion);
                                    foreach ($directFiles as $file) {
                                        $fileVersions = $file['gameVersions'] ?? $file['gameVersion'] ?? [];
                                        if (! is_array($fileVersions)) {
                                            $fileVersions = is_string($fileVersions) ? [$fileVersions] : [];
                                        }

                                        foreach ($fileVersions as $fileVersion) {
                                            if (is_string($fileVersion)) {
                                                $normalizedFileVersion = preg_replace('/\.0+$/', '', $fileVersion);
                                                // Check if it starts with our version (e.g., "1.19" matches "1.19", "1.19.1", "1.19.4")
                                                if (str_starts_with($normalizedFileVersion, $normalizedRequested)) {
                                                    $forgeFiles[] = $file;
                                                    break 2; // Break out of both loops
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Direct CurseForge API call failed, falling back to service', [
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // Fallback to service method if direct call didn't work
                        if (empty($forgeFiles)) {
                            $allForgeFiles = $curseForgeService->getModFiles(306612, null, 'forge');

                            // Filter files that match our Minecraft version pattern
                            $normalizedRequested = preg_replace('/\.0+$/', '', $minecraftVersion);
                            foreach ($allForgeFiles as $file) {
                                $fileVersions = $file['gameVersions'] ?? $file['gameVersion'] ?? [];
                                if (! is_array($fileVersions)) {
                                    $fileVersions = is_string($fileVersions) ? [$fileVersions] : [];
                                }

                                foreach ($fileVersions as $fileVersion) {
                                    if (is_string($fileVersion)) {
                                        $normalizedFileVersion = preg_replace('/\.0+$/', '', $fileVersion);
                                        if (str_starts_with($normalizedFileVersion, $normalizedRequested)) {
                                            $forgeFiles[] = $file;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    \Log::debug('CurseForge API response for Forge', [
                        'files_count' => count($forgeFiles),
                        'first_file' => $forgeFiles[0] ?? null,
                    ]);

                    if (! empty($forgeFiles) && isset($forgeFiles[0]['displayName'])) {
                        // Extract version from display name (e.g., "1.20.1 - 47.2.0" -> "47.2.0" or "1.20.1-47.2.0")
                        $displayName = $forgeFiles[0]['displayName'];
                        \Log::debug('Extracting version from CurseForge display name', [
                            'display_name' => $displayName,
                        ]);

                        // Try to match patterns like "1.20.1-47.2.0" or "47.2.0"
                        if (preg_match('/(\d+\.\d+(?:\.\d+)?)-(\d+\.\d+\.\d+(?:\.\d+)?)/', $displayName, $matches)) {
                            // Format: "1.20.1-47.2.0"
                            $forgeVersion = $matches[0];
                            \Log::info('Found Forge version from CurseForge (full format)', [
                                'version' => $forgeVersion,
                                'display_name' => $displayName,
                            ]);
                        } elseif (preg_match('/-?\s*(\d+\.\d+\.\d+(?:\.\d+)?)$/', $displayName, $matches)) {
                            $forgeVersionNumber = $matches[1];
                            // Construct full version string like "1.20.1-47.2.0"
                            // Use the first file's game version if available, otherwise use requested version
                            $mcVersion = $minecraftVersion;
                            $fileVersions = $forgeFiles[0]['gameVersions'] ?? $forgeFiles[0]['gameVersion'] ?? [];
                            if (! empty($fileVersions) && is_array($fileVersions) && isset($fileVersions[0])) {
                                $mcVersion = is_string($fileVersions[0]) ? $fileVersions[0] : $minecraftVersion;
                            }
                            $forgeVersion = "{$mcVersion}-{$forgeVersionNumber}";
                            \Log::info('Found Forge version from CurseForge', [
                                'version' => $forgeVersion,
                                'display_name' => $displayName,
                            ]);
                        } else {
                            \Log::warning('Could not extract version from CurseForge display name', [
                                'display_name' => $displayName,
                            ]);
                        }
                    } else {
                        \Log::warning('No Forge files found in CurseForge API response', [
                            'minecraft_version' => $minecraftVersion,
                            'files_count' => count($forgeFiles),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to get Forge version from CurseForge', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // If we still don't have a version, try to query Maven metadata to find available versions
            if (! $forgeVersion) {
                try {
                    \Log::debug('Attempting to get Forge version from Maven metadata', [
                        'minecraft_version' => $minecraftVersion,
                    ]);

                    // Query Maven metadata to get available versions
                    // Forge uses a directory structure based on Minecraft version
                    // We need to construct the metadata URL for the specific Minecraft version
                    $metadataUrl = 'https://maven.minecraftforge.net/net/minecraftforge/forge/maven-metadata.xml';
                    $metadataResponse = Http::timeout(30)->get($metadataUrl);

                    if ($metadataResponse->successful()) {
                        $metadataXml = $metadataResponse->body();
                        // Parse XML to extract version numbers
                        if (preg_match_all('/<version>([^<]+)<\/version>/', $metadataXml, $matches)) {
                            $availableVersions = $matches[1];
                            \Log::debug('Found available Forge versions from Maven', [
                                'versions_count' => count($availableVersions),
                                'sample_versions' => array_slice($availableVersions, 0, 10),
                            ]);

                            // Filter versions that start with the Minecraft version (e.g., "1.19-")
                            $normalizedRequested = $minecraftVersion;
                            foreach ($availableVersions as $version) {
                                if (str_starts_with($version, $normalizedRequested.'-')) {
                                    // Test if this version's installer exists
                                    $testInstallerUrl = "https://maven.minecraftforge.net/net/minecraftforge/forge/{$version}/forge-{$version}-installer.jar";
                                    try {
                                        $testResponse = Http::timeout(10)->head($testInstallerUrl);
                                        if ($testResponse->successful()) {
                                            $forgeVersion = $version;
                                            \Log::info('Found Forge version from Maven metadata', [
                                                'version' => $forgeVersion,
                                                'minecraft_version' => $minecraftVersion,
                                            ]);
                                            break;
                                        }
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                            }

                            // If no exact match, try to find the latest version that might work
                            if (! $forgeVersion) {
                                // Sort versions and try the latest ones that start with our Minecraft version
                                $matchingVersions = array_filter($availableVersions, function ($v) use ($normalizedRequested) {
                                    return str_starts_with($v, $normalizedRequested.'-');
                                });

                                if (! empty($matchingVersions)) {
                                    usort($matchingVersions, 'version_compare');
                                    $matchingVersions = array_reverse($matchingVersions);

                                    // Try the latest 10 matching versions
                                    foreach (array_slice($matchingVersions, 0, 10) as $version) {
                                        $testInstallerUrl = "https://maven.minecraftforge.net/net/minecraftforge/forge/{$version}/forge-{$version}-installer.jar";
                                        try {
                                            $testResponse = Http::timeout(10)->head($testInstallerUrl);
                                            if ($testResponse->successful()) {
                                                $forgeVersion = $version;
                                                \Log::info('Found Forge version from Maven (latest matching)', [
                                                    'version' => $forgeVersion,
                                                    'minecraft_version' => $minecraftVersion,
                                                ]);
                                                break;
                                            }
                                        } catch (\Exception $e) {
                                            continue;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::debug('Failed to get Forge version from Maven metadata', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If we still don't have a version, try to construct common version patterns and test them
            if (! $forgeVersion) {
                \Log::debug('Attempting to find Forge version by testing common patterns', [
                    'minecraft_version' => $minecraftVersion,
                ]);

                // Forge versions typically follow patterns like:
                // For 1.19: 1.19-41.1.0, 1.19-41.0.0, etc.
                // For 1.20.1: 1.20.1-47.2.0, 1.20.1-47.1.0, etc.
                // We'll test common forge version numbers
                $possibleForgeVersions = [];

                // Extract major and minor from Minecraft version
                if (preg_match('/^1\.(\d+)(?:\.(\d+))?/', $minecraftVersion, $matches)) {
                    $major = $matches[1];
                    $minor = $matches[2] ?? '0';

                    // Common Forge version patterns based on Minecraft version
                    // For 1.19.x, common versions are 41.x.x
                    // For 1.20.x, common versions are 47.x.x
                    // For 1.21.x, common versions are 47.x.x or higher
                    $baseForgeMajor = null;
                    if ($major === '19') {
                        $baseForgeMajor = 41;
                    } elseif ($major === '20') {
                        $baseForgeMajor = 47;
                    } elseif ($major === '21') {
                        $baseForgeMajor = 47;
                    } elseif ($major >= '22') {
                        // For newer versions, try incrementing from 47
                        $baseForgeMajor = 47 + (intval($major) - 21);
                    }

                    if ($baseForgeMajor !== null) {
                        // Generate possible versions
                        // Test higher minor/patch numbers first (more likely to be the latest)
                        for ($forgeMinor = 3; $forgeMinor >= 0; $forgeMinor--) {
                            // Test a wider range of patch versions
                            for ($forgePatch = 20; $forgePatch >= 0; $forgePatch--) {
                                $possibleForgeVersions[] = "{$minecraftVersion}-{$baseForgeMajor}.{$forgeMinor}.{$forgePatch}";
                            }
                        }
                    }
                }

                // Test each possible version
                foreach ($possibleForgeVersions as $possibleVersion) {
                    $testInstallerUrl = "https://maven.minecraftforge.net/net/minecraftforge/forge/{$possibleVersion}/forge-{$possibleVersion}-installer.jar";
                    try {
                        $testResponse = Http::timeout(10)->head($testInstallerUrl);
                        if ($testResponse->successful()) {
                            $forgeVersion = $possibleVersion;
                            \Log::info('Found Forge version by testing URL patterns', [
                                'version' => $forgeVersion,
                                'minecraft_version' => $minecraftVersion,
                            ]);
                            break;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            // If we still don't have a version, we cannot proceed
            // Forge version format is required: {minecraft_version}-{forge_version}
            if (! $forgeVersion) {
                \Log::error('Could not determine Forge version', [
                    'minecraft_version' => $minecraftVersion,
                ]);

                return false;
            }

            // Download Forge installer from Maven
            $installerUrl = "https://maven.minecraftforge.net/net/minecraftforge/forge/{$forgeVersion}/forge-{$forgeVersion}-installer.jar";

            \Log::info('Downloading Forge installer JAR', [
                'url' => $installerUrl,
                'forge_version' => $forgeVersion,
            ]);

            $installerDownloadResponse = Http::timeout(120)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($installerUrl);

            if (! $installerDownloadResponse->successful()) {
                \Log::error('Failed to download Forge installer', [
                    'url' => $installerUrl,
                    'status' => $installerDownloadResponse->status(),
                ]);

                return false;
            }

            $installerJar = $installerDownloadResponse->body();

            // Validate installer JAR
            if (strlen($installerJar) < 4 || substr($installerJar, 0, 2) !== 'PK') {
                \Log::error('Downloaded Forge installer does not appear to be a valid JAR file', [
                    'file_size' => strlen($installerJar),
                    'first_bytes' => bin2hex(substr($installerJar, 0, 10)),
                ]);

                return false;
            }

            // Save the installer JAR
            $installerPath = $runDir.'/forge-installer.jar';
            $installerWritten = file_put_contents($installerPath, $installerJar);

            if ($installerWritten === false) {
                \Log::error('Failed to write Forge installer to disk', [
                    'file_path' => $installerPath,
                ]);

                return false;
            }

            // Store installer info for run.sh
            $installerInfo = [
                'installer_version' => $forgeVersion,
                'forge_version' => $forgeVersion,
                'minecraft_version' => $minecraftVersion,
            ];

            $infoWritten = file_put_contents($runDir.'/forge-installer-info.txt', json_encode($installerInfo));
            if ($infoWritten === false) {
                \Log::error('Failed to write Forge installer info', [
                    'file_path' => $runDir.'/forge-installer-info.txt',
                ]);

                return false;
            }

            \Log::info('Forge installer downloaded successfully', [
                'installer_path' => $installerPath,
                'forge_version' => $forgeVersion,
                'minecraft_version' => $minecraftVersion,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Error downloading Forge installer', [
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Download vanilla Minecraft server JAR for a specific version.
     */
    private function downloadVanillaServerJar(string $runDir, string $minecraftVersion): bool
    {
        \Log::info('Attempting to download vanilla server JAR', [
            'run_dir' => $runDir,
            'minecraft_version' => $minecraftVersion,
        ]);

        try {
            // First, get the version manifest from Mojang
            $manifestUrl = 'https://piston-meta.mojang.com/mc/game/version_manifest_v2.json';
            $manifestResponse = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($manifestUrl);

            if (! $manifestResponse->successful()) {
                \Log::error('Failed to fetch Minecraft version manifest', [
                    'url' => $manifestUrl,
                    'status' => $manifestResponse->status(),
                ]);

                return false;
            }

            $manifestData = $manifestResponse->json();
            $versions = $manifestData['versions'] ?? [];

            // Find the version entry for the requested Minecraft version
            $versionEntry = null;
            foreach ($versions as $version) {
                if (($version['id'] ?? '') === $minecraftVersion) {
                    $versionEntry = $version;
                    break;
                }
            }

            if (! $versionEntry) {
                \Log::error('Minecraft version not found in manifest', [
                    'minecraft_version' => $minecraftVersion,
                ]);

                return false;
            }

            // Get the version details
            $versionUrl = $versionEntry['url'] ?? null;
            if (! $versionUrl) {
                \Log::error('Version URL not found in manifest entry', [
                    'version_entry' => $versionEntry,
                ]);

                return false;
            }

            $versionResponse = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                ])
                ->get($versionUrl);

            if (! $versionResponse->successful()) {
                \Log::error('Failed to fetch version details', [
                    'url' => $versionUrl,
                    'status' => $versionResponse->status(),
                ]);

                return false;
            }

            $versionData = $versionResponse->json();
            $serverJarUrl = $versionData['downloads']['server']['url'] ?? null;

            if (! $serverJarUrl) {
                \Log::error('Server JAR URL not found in version data', [
                    'version_data' => $versionData,
                ]);

                return false;
            }

            // Download the server JAR
            \Log::debug('Downloading vanilla server JAR', [
                'url' => $serverJarUrl,
            ]);

            $jarResponse = Http::timeout(120) // Longer timeout for large files
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:108.0) Gecko/20100101 Firefox/108.0',
                    'Accept' => 'application/java-archive, application/octet-stream, */*',
                ])
                ->get($serverJarUrl);

            if (! $jarResponse->successful()) {
                \Log::error('Failed to download vanilla server JAR', [
                    'url' => $serverJarUrl,
                    'status' => $jarResponse->status(),
                ]);

                return false;
            }

            $jarContent = $jarResponse->body();

            // Validate that we actually got a JAR file (JAR files are ZIP files, check for ZIP magic bytes)
            if (strlen($jarContent) < 4 || substr($jarContent, 0, 2) !== 'PK') {
                \Log::error('Downloaded file does not appear to be a valid JAR file (missing ZIP magic bytes)', [
                    'file_size' => strlen($jarContent),
                    'first_bytes' => bin2hex(substr($jarContent, 0, 10)),
                ]);

                return false;
            }

            // Save the server JAR
            $serverJarPath = $runDir.'/server.jar';
            $bytesWritten = file_put_contents($serverJarPath, $jarContent);

            if ($bytesWritten === false) {
                \Log::error('Failed to write vanilla server JAR to disk', [
                    'file_path' => $serverJarPath,
                    'run_dir' => $runDir,
                    'directory_exists' => is_dir($runDir),
                    'directory_writable' => is_writable($runDir),
                ]);

                return false;
            }

            \Log::info('Successfully downloaded vanilla server JAR', [
                'minecraft_version' => $minecraftVersion,
                'file_path' => $serverJarPath,
                'file_size' => $bytesWritten,
                'file_exists' => file_exists($serverJarPath),
            ]);

            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('Connection error downloading vanilla server JAR', [
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Exception $e) {
            \Log::error('Error downloading vanilla server JAR', [
                'minecraft_version' => $minecraftVersion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
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
