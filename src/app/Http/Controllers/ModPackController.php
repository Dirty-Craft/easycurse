<?php

namespace App\Http\Controllers;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Services\CurseForgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $curseForgeService = new CurseForgeService;
        $gameVersions = $curseForgeService->getGameVersions();
        $modLoaders = $curseForgeService->getModLoaders();

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

        $curseForgeService = new CurseForgeService;
        $gameVersions = $curseForgeService->getGameVersions();
        $modLoaders = $curseForgeService->getModLoaders();

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
     * Search for mods using CurseForge API.
     */
    public function searchMods(Request $request, string $id)
    {
        $modPack = ModPack::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $curseForgeService = new CurseForgeService;
        $query = trim($validated['query']);

        $results = [];

        // Check if the query looks like a CurseForge URL
        // More lenient check: just see if it contains curseforge.com and looks like a URL
        $isUrl = (str_starts_with($query, 'http://') || str_starts_with($query, 'https://'))
                && str_contains(strtolower($query), 'curseforge.com');

        if ($isUrl) {
            Log::debug('Detected CurseForge URL in search query', [
                'query' => $query,
            ]);

            $modInfo = $curseForgeService->extractModInfoFromUrl($query);
            if ($modInfo) {
                if (isset($modInfo['slug'])) {
                    // Extract slug from URL and search by slug
                    Log::debug('Searching mod by slug from URL', [
                        'slug' => $modInfo['slug'],
                    ]);
                    $mod = $curseForgeService->searchModBySlug($modInfo['slug']);
                    if ($mod) {
                        Log::debug('Found mod by slug', [
                            'mod_id' => $mod['id'] ?? null,
                            'mod_name' => $mod['name'] ?? null,
                        ]);
                        $results[] = $mod;
                    } else {
                        Log::debug('Mod not found by slug', [
                            'slug' => $modInfo['slug'],
                        ]);
                    }
                } elseif (isset($modInfo['mod_id'])) {
                    // Extract mod ID from URL and get mod directly
                    Log::debug('Getting mod by ID from URL', [
                        'mod_id' => $modInfo['mod_id'],
                    ]);
                    $mod = $curseForgeService->getMod($modInfo['mod_id']);
                    if ($mod) {
                        Log::debug('Found mod by ID', [
                            'mod_id' => $mod['id'] ?? null,
                            'mod_name' => $mod['name'] ?? null,
                        ]);
                        $results[] = $mod;
                    } else {
                        Log::debug('Mod not found by ID', [
                            'mod_id' => $modInfo['mod_id'],
                        ]);
                    }
                }
            } else {
                Log::debug('Failed to extract mod info from URL', [
                    'query' => $query,
                ]);
            }
        }

        // If URL search didn't return results, try slug search (if query looks like a slug - lowercase, no spaces)
        if (empty($results) && preg_match('/^[a-z0-9-]+$/', $query)) {
            $mod = $curseForgeService->searchModBySlug($query);
            if ($mod) {
                $results[] = $mod;
            }
        }

        // Also try general search if slug/URL search didn't return results or query doesn't look like a slug
        if (empty($results)) {
            $searchResults = $curseForgeService->searchMods([
                'searchFilter' => $query,
            ]);
            $results = array_merge($results, $searchResults);
        }

        // Remove duplicates by mod ID
        $uniqueResults = [];
        $seenIds = [];
        foreach ($results as $result) {
            if (! isset($seenIds[$result['id']])) {
                $uniqueResults[] = $result;
                $seenIds[$result['id']] = true;
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
            'mod_id' => ['required', 'integer'],
        ]);

        $curseForgeService = new CurseForgeService;
        $files = $curseForgeService->getModFiles(
            $validated['mod_id'],
            $modPack->minecraft_version,
            $modPack->software
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
        ]);

        // Check if mod is already in the mod pack
        if (isset($validated['curseforge_mod_id']) && $validated['curseforge_mod_id']) {
            $existingItem = ModPackItem::where('mod_pack_id', $modPack->id)
                ->where('curseforge_mod_id', $validated['curseforge_mod_id'])
                ->first();

            if ($existingItem) {
                return back()->withErrors([
                    'curseforge_mod_id' => __('messages.modpack.mod_already_added'),
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

        $curseForgeService = new CurseForgeService;
        $downloadLinks = [];

        foreach ($modPack->items as $item) {
            if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
                // Skip items without CurseForge metadata
                continue;
            }

            $downloadInfo = $curseForgeService->getFileDownloadInfo(
                $item->curseforge_mod_id,
                $item->curseforge_file_id
            );

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

        $curseForgeService = new CurseForgeService;
        $downloadLinks = [];

        foreach ($items as $item) {
            if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
                // Skip items without CurseForge metadata
                continue;
            }

            $downloadInfo = $curseForgeService->getFileDownloadInfo(
                $item->curseforge_mod_id,
                $item->curseforge_file_id
            );

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

        if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
            return response()->json([
                'error' => __('messages.modpack.no_download_info'),
            ], 404);
        }

        $curseForgeService = new CurseForgeService;
        $downloadInfo = $curseForgeService->getFileDownloadInfo(
            $item->curseforge_mod_id,
            $item->curseforge_file_id
        );

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

        $curseForgeService = new CurseForgeService;
        $modsWithoutMatchingVersion = [];

        // Check each mod item to see if it has a matching version for the new MC version
        foreach ($modPack->items as $item) {
            if (! $item->curseforge_mod_id) {
                // Skip items without CurseForge mod ID (they can't be validated)
                continue;
            }

            // Get available files for the new version
            $files = $curseForgeService->getModFiles(
                $item->curseforge_mod_id,
                $newMinecraftVersion,
                $newSoftware
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
            if (! $item->curseforge_mod_id) {
                // For items without CurseForge mod ID, copy as-is
                ModPackItem::create([
                    'mod_pack_id' => $newModPack->id,
                    'mod_name' => $item->mod_name,
                    'mod_version' => $item->mod_version,
                    'curseforge_mod_id' => $item->curseforge_mod_id,
                    'curseforge_file_id' => $item->curseforge_file_id,
                    'curseforge_slug' => $item->curseforge_slug,
                    'sort_order' => $sortOrder++,
                ]);
            } else {
                // Get the latest file for the new version
                // We already validated that files exist, so this should always return a file
                $latestFile = $curseForgeService->getLatestModFile(
                    $item->curseforge_mod_id,
                    $newMinecraftVersion,
                    $newSoftware
                );

                if ($latestFile) {
                    ModPackItem::create([
                        'mod_pack_id' => $newModPack->id,
                        'mod_name' => $item->mod_name,
                        'mod_version' => $latestFile['displayName'] ?? $latestFile['fileName'] ?? $item->mod_version,
                        'curseforge_mod_id' => $item->curseforge_mod_id,
                        'curseforge_file_id' => $latestFile['id'],
                        'curseforge_slug' => $item->curseforge_slug,
                        'sort_order' => $sortOrder++,
                    ]);
                } else {
                    // This shouldn't happen since we validated files exist, but log it just in case
                    \Log::warning('getLatestModFile returned null for mod', [
                        'mod_id' => $item->curseforge_mod_id,
                        'mod_name' => $item->mod_name,
                        'minecraft_version' => $newMinecraftVersion,
                        'software' => $newSoftware,
                    ]);
                }
            }
        }

        return redirect()->route('mod-packs.show', $newModPack->id);
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

        $curseForgeService = new CurseForgeService;
        $gameVersions = $curseForgeService->getGameVersions();
        $modLoaders = $curseForgeService->getModLoaders();

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

        $curseForgeService = new CurseForgeService;
        $downloadLinks = [];

        foreach ($modPack->items as $item) {
            if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
                // Skip items without CurseForge metadata
                continue;
            }

            $downloadInfo = $curseForgeService->getFileDownloadInfo(
                $item->curseforge_mod_id,
                $item->curseforge_file_id
            );

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

        $curseForgeService = new CurseForgeService;
        $downloadLinks = [];

        foreach ($items as $item) {
            if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
                // Skip items without CurseForge metadata
                continue;
            }

            $downloadInfo = $curseForgeService->getFileDownloadInfo(
                $item->curseforge_mod_id,
                $item->curseforge_file_id
            );

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

        if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
            return response()->json([
                'error' => __('messages.modpack.no_download_info'),
            ], 404);
        }

        $curseForgeService = new CurseForgeService;
        $downloadInfo = $curseForgeService->getFileDownloadInfo(
            $item->curseforge_mod_id,
            $item->curseforge_file_id
        );

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

        $curseForgeService = new CurseForgeService;
        $updates = [];

        foreach ($modPack->items as $item) {
            if (! $item->curseforge_mod_id) {
                // Skip items without CurseForge mod ID
                continue;
            }

            $latestFile = $curseForgeService->getLatestModFile(
                $item->curseforge_mod_id,
                $modPack->minecraft_version,
                $modPack->software
            );

            if ($latestFile) {
                $latestVersion = $latestFile['displayName'] ?? $latestFile['fileName'] ?? null;
                $currentVersion = $item->mod_version;

                // Only include if there's an update available
                if ($latestVersion && $latestVersion !== $currentVersion) {
                    $updates[] = [
                        'item_id' => $item->id,
                        'mod_name' => $item->mod_name,
                        'current_version' => $currentVersion,
                        'latest_version' => $latestVersion,
                        'latest_file_id' => $latestFile['id'],
                        'file_date' => $latestFile['fileDate'] ?? null,
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

        $curseForgeService = new CurseForgeService;
        $updates = [];

        foreach ($items as $item) {
            if (! $item->curseforge_mod_id) {
                // Skip items without CurseForge mod ID
                continue;
            }

            $latestFile = $curseForgeService->getLatestModFile(
                $item->curseforge_mod_id,
                $modPack->minecraft_version,
                $modPack->software
            );

            if ($latestFile) {
                $latestVersion = $latestFile['displayName'] ?? $latestFile['fileName'] ?? null;
                $currentVersion = $item->mod_version;

                // Only include if there's an update available
                if ($latestVersion && $latestVersion !== $currentVersion) {
                    $updates[] = [
                        'item_id' => $item->id,
                        'mod_name' => $item->mod_name,
                        'current_version' => $currentVersion,
                        'latest_version' => $latestVersion,
                        'latest_file_id' => $latestFile['id'],
                        'file_date' => $latestFile['fileDate'] ?? null,
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

        $curseForgeService = new CurseForgeService;
        $updatedCount = 0;
        $failedCount = 0;

        foreach ($modPack->items as $item) {
            if (! $item->curseforge_mod_id) {
                // Skip items without CurseForge mod ID
                continue;
            }

            $latestFile = $curseForgeService->getLatestModFile(
                $item->curseforge_mod_id,
                $modPack->minecraft_version,
                $modPack->software
            );

            if ($latestFile) {
                $item->update([
                    'mod_version' => $latestFile['displayName'] ?? $latestFile['fileName'] ?? $item->mod_version,
                    'curseforge_file_id' => $latestFile['id'],
                ]);
                $updatedCount++;
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

        $curseForgeService = new CurseForgeService;
        $updatedCount = 0;
        $failedCount = 0;

        foreach ($items as $item) {
            if (! $item->curseforge_mod_id) {
                // Skip items without CurseForge mod ID
                $failedCount++;

                continue;
            }

            $latestFile = $curseForgeService->getLatestModFile(
                $item->curseforge_mod_id,
                $modPack->minecraft_version,
                $modPack->software
            );

            if ($latestFile) {
                $item->update([
                    'mod_version' => $latestFile['displayName'] ?? $latestFile['fileName'] ?? $item->mod_version,
                    'curseforge_file_id' => $latestFile['id'],
                ]);
                $updatedCount++;
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
}
