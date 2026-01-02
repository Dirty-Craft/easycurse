<?php

namespace App\Http\Controllers;

use App\Models\ModSet;
use App\Models\ModSetItem;
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
     * Store a new mod item for a mod set.
     */
    public function storeItem(Request $request, string $id)
    {
        $modSet = ModSet::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'mod_name' => ['required', 'string', 'max:255'],
            'mod_version' => ['required', 'string', 'max:255'],
        ]);

        $maxSortOrder = ModSetItem::where('mod_set_id', $modSet->id)->max('sort_order') ?? 0;

        ModSetItem::create([
            ...$validated,
            'mod_set_id' => $modSet->id,
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
}
