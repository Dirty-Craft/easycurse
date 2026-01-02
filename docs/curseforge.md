# CurseForge Integration

This project integrates with the CurseForge API to enable mod search and version selection for mod packs.

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

1. Search for mods by name or slug
2. Select a mod from search results
3. Choose from available versions filtered by the mod pack's Minecraft version and software type
4. The selected mod is saved with CurseForge metadata (mod ID, file ID, slug)

The integration ensures version compatibility by automatically filtering files based on the mod pack's configuration.

## API Endpoints

The following endpoints are available for mod pack management:

- `GET /mod-packs/{id}/search-mods?query={query}` - Search for mods
- `GET /mod-packs/{id}/mod-files?mod_id={mod_id}` - Get available files for a mod

## Database Schema

Mod set items store CurseForge metadata:

- `curseforge_mod_id` - The CurseForge mod ID
- `curseforge_file_id` - The selected file ID
- `curseforge_slug` - The mod's slug for reference

