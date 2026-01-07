# CurseForge Integration

This project integrates with the CurseForge API to enable mod search and version selection for mod packs.

## Unified Mod Service

The application uses a unified `ModService` class (`src/app/Services/ModService.php`) that acts as an interface for both `CurseForgeService` and `ModrinthService`. This eliminates the need for conditional service usage throughout the codebase.

**For most use cases, you should use `ModService` instead of directly instantiating `CurseForgeService` or `ModrinthService`.**

The `ModService` automatically:
- Merges results from both platforms when searching mods
- Combines game versions and mod loaders from both sources
- Handles source detection and routing to the appropriate service
- Normalizes data structures between platforms

## Service Class

The `CurseForgeService` class (`src/app/Services/CurseForgeService.php`) handles all interactions with the CurseForge API. It provides methods for:

- Searching mods by slug or name
- Retrieving mod details
- Fetching mod files filtered by Minecraft version and mod loader (Forge/Fabric)
- Getting file dependencies

## Configuration

The service is configured in `src/config/services.php` and uses environment variables:

```env
CURSEFORGE_API_KEY=your_api_key_here
```

Optional configuration (with defaults):
- `CURSEFORGE_BASE_URL` - API base URL (default: `https://api.curseforge.com/v1/`)
- `CURSEFORGE_MINECRAFT_GAME_ID` - Minecraft game ID (default: `432`)
- `CURSEFORGE_MINECRAFT_MODS_CLASS_ID` - Mods class ID (default: `6`)

## Usage in Mod Sets

When adding mods to a mod pack, users can:

1. Search for mods by name or slug (searches both CurseForge and Modrinth)
2. Select a mod from search results (results from both platforms are merged)
3. Choose from available versions filtered by the mod pack's Minecraft version and software type
4. The selected mod is saved with platform-specific metadata (CurseForge: mod ID, file ID, slug; Modrinth: project ID, version ID, slug)

The integration ensures version compatibility by automatically filtering files based on the mod pack's configuration. The `ModService` handles routing to the appropriate service based on the mod's source.

## API Endpoints

The following endpoints are available for mod pack management:

- `GET /mod-packs/{id}/search-mods?query={query}` - Search for mods (searches both CurseForge and Modrinth via `ModService`)
- `GET /mod-packs/{id}/mod-files?mod_id={mod_id}&source={curseforge|modrinth}` - Get available files/versions for a mod (requires `source` parameter)

## Database Schema

Mod set items store CurseForge metadata:

- `curseforge_mod_id` - The CurseForge mod ID
- `curseforge_file_id` - The selected file ID
- `curseforge_slug` - The mod's slug for reference

