<?php

namespace App\Http\Controllers;

use App\Models\ModSet;
use App\Models\ModSetItem;
use App\Services\CurseForgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ModSetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $modSets = ModSet::where('user_id', Auth::id())
            ->with('items')
            ->latest()
            ->get();

        return Inertia::render('ModSets/Index', [
            'modSets' => $modSets,
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
            'software' => ['required', Rule::in(['forge', 'fabric'])],
            'description' => ['nullable', 'string'],
        ]);

        $modSet = ModSet::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('mod-sets.show', $modSet->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        return Inertia::render('ModSets/Show', [
            'modSet' => $modSet,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'minecraft_version' => ['required', 'string', 'max:255'],
            'software' => ['required', Rule::in(['forge', 'fabric'])],
            'description' => ['nullable', 'string'],
        ]);

        $modSet->update($validated);

        return redirect()->route('mod-sets.show', $modSet->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);
        $modSet->delete();

        return redirect()->route('mod-sets.index');
    }

    /**
     * Search for mods using CurseForge API.
     */
    public function searchMods(Request $request, string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $curseForgeService = new CurseForgeService;
        $query = trim($validated['query']);

        // Try searching by slug first (if query looks like a slug - lowercase, no spaces)
        $results = [];
        if (preg_match('/^[a-z0-9-]+$/', $query)) {
            $mod = $curseForgeService->searchModBySlug($query);
            if ($mod) {
                $results[] = $mod;
            }
        }

        // Also try general search if slug search didn't return results or query doesn't look like a slug
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
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'mod_id' => ['required', 'integer'],
        ]);

        $curseForgeService = new CurseForgeService;
        $files = $curseForgeService->getModFiles(
            $validated['mod_id'],
            $modSet->minecraft_version,
            $modSet->software
        );

        return response()->json([
            'data' => $files,
        ]);
    }

    /**
     * Store a new mod item for a mod set.
     */
    public function storeItem(Request $request, string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'mod_name' => ['required', 'string', 'max:255'],
            'mod_version' => ['required', 'string', 'max:255'],
            'curseforge_mod_id' => ['nullable', 'integer'],
            'curseforge_file_id' => ['nullable', 'integer'],
            'curseforge_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $maxSortOrder = ModSetItem::where('mod_set_id', $modSet->id)->max('sort_order') ?? 0;

        ModSetItem::create([
            'mod_set_id' => $modSet->id,
            'mod_name' => $validated['mod_name'],
            'mod_version' => $validated['mod_version'],
            'curseforge_mod_id' => $validated['curseforge_mod_id'] ?? null,
            'curseforge_file_id' => $validated['curseforge_file_id'] ?? null,
            'curseforge_slug' => $validated['curseforge_slug'] ?? null,
            'sort_order' => $maxSortOrder + 1,
        ]);

        return redirect()->route('mod-sets.show', $modSet->id);
    }

    /**
     * Remove a mod item from a mod set.
     */
    public function destroyItem(string $id, string $itemId)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);
        $item = ModSetItem::where('mod_set_id', $modSet->id)->findOrFail($itemId);
        $item->delete();

        return redirect()->route('mod-sets.show', $modSet->id);
    }

    /**
     * Get download links for all mod items in a mod set.
     */
    public function getDownloadLinks(string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())
            ->with('items')
            ->findOrFail($id);

        $curseForgeService = new CurseForgeService;
        $downloadLinks = [];

        foreach ($modSet->items as $item) {
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

        return response()->json([
            'data' => $downloadLinks,
        ]);
    }

    /**
     * Get download link for a specific mod item.
     */
    public function getItemDownloadLink(string $id, string $itemId)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);
        $item = ModSetItem::where('mod_set_id', $modSet->id)->findOrFail($itemId);

        if (! $item->curseforge_mod_id || ! $item->curseforge_file_id) {
            return response()->json([
                'error' => 'This mod item does not have CurseForge download information.',
            ], 404);
        }

        $curseForgeService = new CurseForgeService;
        $downloadInfo = $curseForgeService->getFileDownloadInfo(
            $item->curseforge_mod_id,
            $item->curseforge_file_id
        );

        if (! $downloadInfo) {
            return response()->json([
                'error' => 'Unable to retrieve download information for this mod.',
            ], 404);
        }

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
}
