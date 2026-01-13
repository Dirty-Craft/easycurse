# Console Commands

This project includes several console commands that automate various maintenance and notification tasks. Commands are defined in `src/app/Console/Commands/` and scheduled in `src/routes/console.php`.

## Available Commands

### `mods:check-updates`

Checks for mod updates and notifies mod pack owners when newer compatible versions are available.

- **Command**: `docker compose exec app php artisan mods:check-updates`
- **Implementation**: `src/app/Console/Commands/CheckModUpdates.php`
- **Schedule**: Runs daily at 02:00 UTC

#### How it works

The command creates a grouped dataset by joining `mod_pack_items` → `mod_packs` and grouping by:

- `mod_packs.software`
- `mod_packs.minecraft_version`
- `mod_pack_items.curseforge_mod_id` / `mod_pack_items.modrinth_project_id`

This represents the unique (mod + loader + Minecraft version) combinations that need checking, avoiding duplicate API calls.

For each grouped entry, the command:

- Calls `ModService::getLatestModFile($modId, $minecraftVersion, $software, $source)`
- Extracts the latest version string from the platform response
- Compares it against the mod version stored on each affected `mod_pack_items.mod_version`

#### Notifications

- **Notification class**: `src/app/Notifications/ModUpdateAvailable.php`
- **Channel**: mail (`via()` returns `['mail']`)

Emails include a message like: "A new compatible version of \<mod name\> is available for Fabric 1.21.1." plus the current/new versions.

#### Notification cooldown

To avoid spamming users who don't update immediately, the system tracks `last_update_notified_at` on each `mod_pack_items` row. A notification is only sent if:

- The item has never been notified (`last_update_notified_at` is null), or
- More than 1 month has passed since the last notification

This ensures users receive at most one reminder per month for each mod in their mod pack.

### `minecraft:check-version-updates`

Checks mod packs with version update reminders and notifies owners when all mods become compatible with a target Minecraft version.

- **Command**: `docker compose exec app php artisan minecraft:check-version-updates`
- **Implementation**: `src/app/Console/Commands/CheckMinecraftVersionUpdates.php`
- **Schedule**: Runs daily at 12:00 UTC

#### How it works

1. **Setting a reminder**: When a user attempts to change a mod pack's Minecraft version and some mods don't have compatible versions, an error message is shown with a "Remind me once available" button.
2. **Storing the reminder**: Clicking the button saves the target Minecraft version and software (loader) to the `mod_packs` table in the `minecraft_update_reminder_version` and `minecraft_update_reminder_software` fields.
3. **Checking for updates**: This scheduled command runs daily to check all mod packs with reminders set.
4. **Notification**: When ALL mods in a mod pack have compatible versions for the target version/software, an email notification is sent to the user and the reminder fields are cleared.

#### Update detection

For each mod pack with a reminder set, the command:

- Loads all mod items in the mod pack
- Checks each mod for compatible versions using `ModService::getModFiles()` with the target Minecraft version and software
- If ALL mods have compatible versions, sends a notification and clears the reminder fields
- If any mods are still incompatible, the reminder remains active

#### Notifications

- **Notification class**: `src/app/Notifications/MinecraftVersionUpdateAvailable.php`
- **Channel**: mail (`via()` returns `['mail']`)

Emails include a message indicating that all mods in the mod pack now have compatible versions for the target Minecraft version and loader, with a link to view the mod pack.

## Running Commands

Commands can be run manually using:

```bash
docker compose exec app php artisan <command-name>
```

## Scheduling

Scheduled commands run automatically via Laravel's task scheduler. The scheduler is handled in the Docker setup via the `scheduler` service in `docker-compose.yml`, which runs `php artisan schedule:run` every 60 seconds.

