# Mod Update Reminders

This project includes an automated reminder system that emails mod pack owners when a newer *compatible* version of a mod is available for their mod pack's Minecraft version and loader.

## Overview

The reminder system is implemented as a scheduled console command that:

- Builds a distinct “what to check” set from `mod_pack_items` joined with `mod_packs`
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

```php
Schedule::command('mods:check-updates')
    ->daily()
    ->at('02:00')
    ->timezone('UTC');
```

## Testing

Feature test: `src/tests/Feature/CheckModUpdatesCommandTest.php`

```shell
docker compose exec app php artisan test --filter CheckModUpdatesCommandTest
```