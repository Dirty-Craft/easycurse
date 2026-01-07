<?php

namespace App\Console\Commands;

use App\Models\ModPack;
use App\Models\ModPackItem;
use App\Notifications\ModUpdateAvailable;
use App\Services\ModService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckModUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mods:check-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for mod updates and notify mod pack owners';

    /**
     * Execute the console command.
     */
    public function handle(ModService $modService): int
    {
        $this->info('Checking for mod updates...');

        // Build a distinct result set by grouping mod_pack_items with mod_packs
        // Group by: software, minecraft_version, and mod_id (curseforge_mod_id or modrinth_project_id)
        // This avoids duplicate checks for the same mod/version/software combination
        $groupedItems = DB::table('mod_pack_items')
            ->join('mod_packs', 'mod_pack_items.mod_pack_id', '=', 'mod_packs.id')
            ->select(
                'mod_packs.software',
                'mod_packs.minecraft_version',
                'mod_pack_items.curseforge_mod_id',
                'mod_pack_items.modrinth_project_id',
                'mod_pack_items.source',
                DB::raw('MAX(mod_pack_items.mod_name) as mod_name')
            )
            ->where(function ($query) {
                $query->whereNotNull('mod_pack_items.curseforge_mod_id')
                    ->orWhereNotNull('mod_pack_items.modrinth_project_id');
            })
            ->groupBy(
                'mod_packs.software',
                'mod_packs.minecraft_version',
                'mod_pack_items.curseforge_mod_id',
                'mod_pack_items.modrinth_project_id',
                'mod_pack_items.source'
            )
            ->get();

        $this->info("Found {$groupedItems->count()} unique mod/version/software combinations to check.");

        $updatesFound = 0;
        $notificationsSent = 0;
        $errors = 0;

        foreach ($groupedItems as $item) {
            try {
                // Determine mod ID and source
                $modId = $item->curseforge_mod_id ?? $item->modrinth_project_id;
                $source = $item->source ?? ($item->curseforge_mod_id ? 'curseforge' : 'modrinth');

                if (! $modId) {
                    continue;
                }

                // Get the latest compatible version
                $latestFile = $modService->getLatestModFile(
                    $modId,
                    $item->minecraft_version,
                    $item->software,
                    $source
                );

                if (! $latestFile) {
                    continue;
                }

                // Extract version from the latest file
                $latestVersion = $this->extractVersion($latestFile, $source);
                if (! $latestVersion) {
                    continue;
                }

                // Find all mod packs that include this mod for the same software and Minecraft version
                $affectedModPacks = $this->findAffectedModPacks(
                    $modId,
                    $source,
                    $item->software,
                    $item->minecraft_version
                );

                // Check each mod pack to see if it needs an update
                foreach ($affectedModPacks as $modPack) {
                    // Get the current version of this mod in this mod pack
                    $modPackItem = ModPackItem::where('mod_pack_id', $modPack->id)
                        ->where(function ($query) use ($modId, $source) {
                            if ($source === 'curseforge') {
                                $query->where('curseforge_mod_id', $modId);
                            } else {
                                $query->where('modrinth_project_id', $modId);
                            }
                        })
                        ->first();

                    if (! $modPackItem) {
                        continue;
                    }

                    // Compare versions
                    if ($this->isNewerVersion($latestVersion, $modPackItem->mod_version)) {
                        $updatesFound++;

                        // Check if we've already notified about this update recently (within 1 month)
                        if ($this->wasRecentlyNotified($modPackItem)) {
                            $this->line("Skipping notification (already notified within 1 month): {$modPackItem->mod_name} for mod pack: {$modPack->name}");

                            continue;
                        }

                        // Send notification to mod pack owner
                        $user = $modPack->user;
                        if ($user && $user->email) {
                            $user->notify(new ModUpdateAvailable(
                                $modPackItem->mod_name,
                                $modPackItem->mod_version,
                                $latestVersion,
                                $item->software,
                                $item->minecraft_version,
                                $modPack->id,
                                $modPack->name
                            ));

                            // Update the last notification timestamp
                            $modPackItem->update(['last_update_notified_at' => now()]);

                            $notificationsSent++;
                        }

                        $this->line("Update found: {$modPackItem->mod_name} ({$modPackItem->mod_version} -> {$latestVersion}) for mod pack: {$modPack->name}");
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error checking mod update', [
                    'item' => $item,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->warn("Error checking mod: {$e->getMessage()}");
            }
        }

        $this->info("Update check complete. Found {$updatesFound} updates, sent {$notificationsSent} notifications, encountered {$errors} errors.");

        return Command::SUCCESS;
    }

    /**
     * Extract version string from mod file/version data.
     */
    private function extractVersion(array $fileData, string $source): ?string
    {
        if ($source === 'curseforge') {
            // CurseForge files have 'displayName' or 'fileName' with version
            return $fileData['displayName'] ?? $fileData['fileName'] ?? null;
        } else {
            // Modrinth versions have 'version_number'
            return $fileData['version_number'] ?? null;
        }
    }

    /**
     * Check if a version is newer than another.
     */
    private function isNewerVersion(string $newVersion, string $currentVersion): bool
    {
        // Normalize versions for comparison
        $newVersionNormalized = $this->normalizeVersion($newVersion);
        $currentVersionNormalized = $this->normalizeVersion($currentVersion);

        // Use version_compare to check if new version is greater
        return version_compare($newVersionNormalized, $currentVersionNormalized, '>');
    }

    /**
     * Normalize version string for comparison.
     */
    private function normalizeVersion(string $version): string
    {
        // Remove common prefixes/suffixes and extract semantic version
        // Examples: "1.20.1", "v1.20.1", "1.20.1-release", "modname-1.20.1"
        $version = preg_replace('/^[^0-9]+/', '', $version); // Remove leading non-numeric
        $version = preg_replace('/[^0-9.]+.*$/', '', $version); // Remove trailing non-numeric after version

        return $version;
    }

    /**
     * Find all mod packs that include a specific mod for the same software and Minecraft version.
     */
    private function findAffectedModPacks(
        string|int $modId,
        string $source,
        string $software,
        string $minecraftVersion
    ): \Illuminate\Database\Eloquent\Collection {
        $query = ModPack::query()
            ->where('software', $software)
            ->where('minecraft_version', $minecraftVersion)
            ->whereHas('items', function ($query) use ($modId, $source) {
                if ($source === 'curseforge') {
                    $query->where('curseforge_mod_id', $modId);
                } else {
                    $query->where('modrinth_project_id', $modId);
                }
            })
            ->with('user');

        return $query->get();
    }

    /**
     * Check if a mod pack item was notified about an update within the last month.
     */
    private function wasRecentlyNotified(ModPackItem $modPackItem): bool
    {
        if (! $modPackItem->last_update_notified_at) {
            return false;
        }

        return $modPackItem->last_update_notified_at->greaterThan(Carbon::now()->subMonth());
    }
}
