# Mod Update Reminders

This project includes two automated reminder systems:

1. **Mod Update Reminders**: Emails mod pack owners when a newer *compatible* version of a mod is available for their mod pack's Minecraft version and loader.
2. **Minecraft Version Update Reminders**: Emails mod pack owners when all mods in their mod pack become available for a target Minecraft version and loader.

## Mod Update Reminders

### Overview

The mod update reminder system is implemented as a scheduled console command that:

- Builds a distinct "what to check" set from `mod_pack_items` joined with `mod_packs`
- Checks the latest compatible version via `ModService`
- Notifies owners of affected mod packs by email

## Command

- **Command**: `docker compose exec app php artisan mods:check-updates`
- **Implementation**: `src/app/Console/Commands/CheckModUpdates.php`

## How it works

### Query strategy (avoids duplicate checks)

The command creates a grouped dataset by joining `mod_pack_items` → `mod_packs` and grouping by:

- `mod_packs.software`
- `mod_packs.minecraft_version`
- `mod_pack_items.curseforge_mod_id` / `mod_pack_items.modrinth_project_id`

This represents the unique (mod + loader + Minecraft version) combinations that need checking.

### Update detection

For each grouped entry, the command:

- Calls `ModService::getLatestModFile($modId, $minecraftVersion, $software, $source)`
- Extracts the latest version string from the platform response
- Compares it against the mod version stored on each affected `mod_pack_items.mod_version`

## Notifications

- **Notification class**: `src/app/Notifications/ModUpdateAvailable.php`
- **Channel**: mail (`via()` returns `['mail']`)

Emails include a message like: "A new compatible version of \<mod name\> is available for Fabric 1.21.1." plus the current/new versions.

### Notification cooldown

To avoid spamming users who don't update immediately, the system tracks `last_update_notified_at` on each `mod_pack_items` row. A notification is only sent if:

- The item has never been notified (`last_update_notified_at` is null), or
- More than 1 month has passed since the last notification

This ensures users receive at most one reminder per month for each mod in their mod pack.

## Scheduling

The command is scheduled in `src/routes/console.php`:

## Minecraft Version Update Reminders

### Overview

The Minecraft version update reminder system allows users to set reminders when attempting to change a mod pack's Minecraft version. If some mods don't have compatible versions available, users can click "Remind me once available" to be notified when all mods become compatible.

### How it works

1. **Setting a reminder**: When a user attempts to change a mod pack's Minecraft version and some mods don't have compatible versions, an error message is shown with a "Remind me once available" button.
2. **Storing the reminder**: Clicking the button saves the target Minecraft version and software (loader) to the `mod_packs` table in the `minecraft_update_reminder_version` and `minecraft_update_reminder_software` fields.
3. **Checking for updates**: A scheduled command runs daily to check all mod packs with reminders set.
4. **Notification**: When ALL mods in a mod pack have compatible versions for the target version/software, an email notification is sent to the user and the reminder fields are cleared.

### Command

- **Command**: `docker compose exec app php artisan minecraft:check-version-updates`
- **Implementation**: `src/app/Console/Commands/CheckMinecraftVersionUpdates.php`

### Update detection

For each mod pack with a reminder set, the command:

- Loads all mod items in the mod pack
- Checks each mod for compatible versions using `ModService::getModFiles()` with the target Minecraft version and software
- If ALL mods have compatible versions, sends a notification and clears the reminder fields
- If any mods are still incompatible, the reminder remains active

### Notifications

- **Notification class**: `src/app/Notifications/MinecraftVersionUpdateAvailable.php`
- **Channel**: mail (`via()` returns `['mail']`)

Emails include a message indicating that all mods in the mod pack now have compatible versions for the target Minecraft version and loader, with a link to view the mod pack.

### Scheduling

The command is scheduled in `src/routes/console.php`:
