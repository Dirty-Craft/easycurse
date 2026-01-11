<?php

namespace App\Http\Controllers;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Models\ModPackRun;
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

        $modService = new ModService;
        $gameVersions = $modService->getGameVersions();
        $modLoaders = $modService->getModLoaders();

        return Inertia::render('ModPacks/Show', [
            'modPack' => $modPack,
            'activeRun' => $activeRun,
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

        return redirect()->route('mod-packs.show', $modPack->id);
    }

    /**
     * Remove a mod item from a mod pack.
     */
    public function destroyItem(string $id, string $itemId)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);
        $item = ModPackItem::where('mod_pack_id', $modPack->id)->findOrFail($itemId);
        $item->delete();

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
        $items = ModPackItem::where('mod_pack_id', $modPack->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count($itemIds)) {
            return response()->json([
                'error' => __('messages.modpack.invalid_item_ids'),
            ], 400);
        }

        // Delete all items
        ModPackItem::whereIn('id', $itemIds)->delete();

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

        // Create a new run with is_completed = false
        $run = ModPackRun::create([
            'mod_pack_id' => $modPack->id,
            'is_completed' => false,
        ]);

        // Create directory structure for the run
        $runDir = '/shared/virtual/'.$run->id;
        $modsDir = $runDir.'/mods';

        if (! is_dir($runDir)) {
            mkdir($runDir, 0755, true);
        }
        if (! is_dir($modsDir)) {
            mkdir($modsDir, 0755, true);
        }

        // Download mod loader from ServerJars
        $loaderDownloaded = $this->downloadModLoaderFromServerJars(
            $runDir,
            $modPack->software,
            $modPack->minecraft_version
        );

        // Initialize server JAR download status
        // For Forge, server JAR is not needed (it's bundled)
        // For Fabric, Quilt, and NeoForge, we need to download it separately
        $serverJarDownloaded = ! in_array($modPack->software, ['fabric', 'quilt', 'neoforge']);

        if (! $loaderDownloaded) {
            \Log::warning('Failed to download mod loader from ServerJars', [
                'run_id' => $run->id,
                'software' => $modPack->software,
                'minecraft_version' => $modPack->minecraft_version,
            ]);
        } else {
            // For Fabric, the installer will download the server JAR, so we skip the separate download
            // For Quilt and NeoForge, we need to download the vanilla Minecraft server JAR
            if (in_array($modPack->software, ['quilt', 'neoforge'])) {
                $serverJarDownloaded = $this->downloadVanillaServerJar(
                    $runDir,
                    $modPack->minecraft_version
                );

                if (! $serverJarDownloaded) {
                    \Log::warning('Failed to download vanilla server JAR', [
                        'run_id' => $run->id,
                        'minecraft_version' => $modPack->minecraft_version,
                    ]);
                }
            } elseif ($modPack->software === 'fabric') {
                // For Fabric, the installer handles everything, so server JAR is already handled
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
                $runShContent .= "java -jar fabric-installer.jar server -mcversion {$installerInfo['minecraft_version']} -loader {$installerInfo['loader_version']} -downloadMinecraft > installer.log 2>&1\n";
                $runShContent .= "# Run the generated Fabric server launcher\n";
                $runShContent .= "java -jar fabric-server-launch.jar > logs.txt 2>&1\n";
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

        return response()->json([
            'data' => $run,
            'downloaded_count' => $downloadedCount,
            'failed_count' => $failedCount,
        ]);
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

        // For Fabric, always use the installer approach
        if ($software === 'fabric') {
            return $this->downloadFabricInstaller($runDir, $minecraftVersion);
        }

        // Map software types to ServerJars API types
        $serverJarsTypeMap = [
            'forge' => 'modded/forge',
            'quilt' => 'modded/quilt',
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
                } else {
                    // For other loaders, we might need to handle differently
                    \Log::error('ServerJars returned HTML for non-Fabric loader, cannot proceed', [
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
