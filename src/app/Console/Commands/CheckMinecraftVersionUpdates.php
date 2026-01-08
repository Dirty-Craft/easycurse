<?php

namespace App\Console\Commands;

use App\Models\ModPack;
use App\Notifications\MinecraftVersionUpdateAvailable;
use App\Services\ModService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckMinecraftVersionUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'minecraft:check-version-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for Minecraft version updates and notify mod pack owners when all mods become available';

    /**
     * Execute the console command.
     */
    public function handle(ModService $modService): int
    {
        $this->info('Checking for Minecraft version updates...');

        // Load mod packs that have reminders set
        $modPacksWithReminders = ModPack::whereNotNull('minecraft_update_reminder_version')
            ->whereNotNull('minecraft_update_reminder_software')
            ->with(['items', 'user'])
            ->get();

        $this->info("Found {$modPacksWithReminders->count()} mod pack(s) with reminders set.");

        $notificationsSent = 0;
        $errors = 0;

        foreach ($modPacksWithReminders as $modPack) {
            try {
                $targetMinecraftVersion = $modPack->minecraft_update_reminder_version;
                $targetSoftware = $modPack->minecraft_update_reminder_software;

                $this->line("Checking mod pack: {$modPack->name} for {$targetSoftware} {$targetMinecraftVersion}");

                // Check if all mods have compatible versions
                $allModsCompatible = true;
                $modsWithoutVersion = [];

                foreach ($modPack->items as $item) {
                    $source = $item->source;
                    if (! $source) {
                        // Determine source from item data
                        if ($item->curseforge_mod_id) {
                            $source = 'curseforge';
                        } elseif ($item->modrinth_project_id) {
                            $source = 'modrinth';
                        } else {
                            // Skip items without platform metadata
                            continue;
                        }
                    }

                    $modId = $source === 'curseforge' ? $item->curseforge_mod_id : $item->modrinth_project_id;
                    if (! $modId) {
                        continue;
                    }

                    // Check if mod has compatible version for target MC version
                    $files = $modService->getModFiles(
                        $modId,
                        $targetMinecraftVersion,
                        $targetSoftware,
                        $source
                    );

                    // If no files found for this mod with the target version, mark as incompatible
                    if (empty($files)) {
                        $allModsCompatible = false;
                        $modsWithoutVersion[] = $item->mod_name;
                    }
                }

                // If all mods are compatible, send notification and clear reminder
                if ($allModsCompatible) {
                    $user = $modPack->user;
                    if ($user && $user->email) {
                        $user->notify(new MinecraftVersionUpdateAvailable(
                            $modPack->name,
                            $targetMinecraftVersion,
                            $targetSoftware,
                            $modPack->id
                        ));

                        $notificationsSent++;
                        $this->line("✓ All mods compatible for {$modPack->name}. Notification sent.");
                    }

                    // Clear the reminder fields
                    $modPack->update([
                        'minecraft_update_reminder_version' => null,
                        'minecraft_update_reminder_software' => null,
                    ]);
                } else {
                    $this->line("✗ Some mods still incompatible for {$modPack->name}: ".implode(', ', $modsWithoutVersion));
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error checking Minecraft version update', [
                    'mod_pack_id' => $modPack->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->warn("Error checking mod pack {$modPack->id}: {$e->getMessage()}");
            }
        }

        $this->info("Version update check complete. Sent {$notificationsSent} notifications, encountered {$errors} errors.");

        return Command::SUCCESS;
    }
}
