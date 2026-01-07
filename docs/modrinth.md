# Modrinth Integration

This project integrates with the Modrinth API to enable mod search and version selection for mod packs. The application supports both CurseForge and Modrinth, allowing users to load mods from either platform.

## Service Class

The `ModrinthService` class (`src/app/Services/ModrinthService.php`) handles all interactions with the Modrinth API. It provides methods for:

- Searching mods by slug or name
- Retrieving project details
- Fetching project versions filtered by Minecraft version and mod loader (Forge/Fabric/Quilt/NeoForge)
- Getting version dependencies
- Generating download URLs

## Configuration

The service is configured in `src/config/services.php` and uses environment variables:

```env
MODRINTH_BASE_URL=https://api.modrinth.com/v2/
MODRINTH_USER_AGENT=EasyCurse/1.0.0 (contact@example.com)
```

Optional configuration (with defaults):
- `MODRINTH_BASE_URL` - API base URL (default: `https://api.modrinth.com/v2/`)
- `MODRINTH_USER_AGENT` - User agent string for API requests (default: `EasyCurse/1.0.0 (contact@example.com)`)

**Note:** Modrinth API requires a User-Agent header for all requests. Make sure to set a proper user agent that identifies your application.

## Usage in Mod Sets

When adding mods to a mod pack, users can:

1. Search for mods by name, slug, or URL (supports both CurseForge and Modrinth URLs)
2. Select a mod from search results (results from both platforms are shown)
3. Choose from available versions filtered by the mod pack's Minecraft version and software type
4. The selected mod is saved with platform-specific metadata:
   - For Modrinth: project ID, version ID, and slug
   - For CurseForge: mod ID, file ID, and slug
5. The `source` field tracks which platform the mod comes from (`curseforge` or `modrinth`)

The integration ensures version compatibility by automatically filtering versions based on the mod pack's configuration. Both platforms are searched simultaneously, and results are merged and deduplicated.

## API Endpoints

The following endpoints support both CurseForge and Modrinth:

- `GET /mod-packs/{id}/search-mods?query={query}` - Search for mods (searches both platforms)
- `GET /mod-packs/{id}/mod-files?mod_id={mod_id}&source={curseforge|modrinth}` - Get available files/versions for a mod

**Note:** The `mod-files` endpoint requires a `source` parameter to specify which platform to query (`curseforge` or `modrinth`).

## Database Schema

Mod pack items store metadata for both platforms:

### Modrinth Fields:
- `modrinth_project_id` - The Modrinth project ID (string)
- `modrinth_version_id` - The selected version ID (string)
- `modrinth_slug` - The project's slug for reference (string)

### CurseForge Fields:
- `curseforge_mod_id` - The CurseForge mod ID (integer)
- `curseforge_file_id` - The selected file ID (integer)
- `curseforge_slug` - The mod's slug for reference (string)

### Common Fields:
- `source` - Platform identifier (`curseforge` or `modrinth`) - helps identify which platform's metadata is populated
- `mod_name` - Display name of the mod
- `mod_version` - Version string of the selected mod file/version

## Platform Detection

The application automatically detects which platform a mod belongs to based on:

1. The `source` field if explicitly set
2. Presence of platform-specific IDs:
   - If `curseforge_mod_id` is set → CurseForge
   - If `modrinth_project_id` is set → Modrinth

## URL Support

The search functionality supports Modrinth URLs in the following formats:

- `https://modrinth.com/mod/{slug}`
- `https://www.modrinth.com/mod/{slug}`
- `https://modrinth.com/project/{slug}`
- `https://www.modrinth.com/project/{slug}`

The service extracts the slug from these URLs and searches for the corresponding project.

## Mod Loaders

Modrinth supports the following mod loaders:

- **Forge** - Traditional mod loader
- **Fabric** - Lightweight mod loader
- **Quilt** - Fork of Fabric with additional features
- **NeoForge** - Fork of Forge

These are automatically merged with CurseForge's loader list when displaying options to users.

## Game Versions

Game versions from Modrinth are automatically merged with CurseForge versions. The application:

1. Fetches versions from both APIs
2. Removes duplicates (by version name)
3. Sorts versions (newest first)
4. Displays a unified list to users

Only release versions are included from Modrinth (snapshots and betas are filtered out for consistency).

## Download URLs

Modrinth provides direct download URLs in the version data. The service:

1. Retrieves version information
2. Extracts the primary file (marked as primary, or first file if none marked)
3. Returns the download URL and filename

Download URLs are cached for 6 hours to reduce API calls.

## Error Handling

The service handles API errors gracefully:

- Failed requests are logged but don't break the application
- Errors are not cached, allowing retries on subsequent requests
- Missing data returns `null` or empty arrays rather than throwing exceptions
- The application continues to work even if one platform's API is unavailable

## Caching

Modrinth API responses are cached to reduce API calls:

- Project details: 12 hours
- Version lists: 1 hour
- Individual versions: 2 hours
- Search results: 30 minutes
- Download URLs: 6 hours
- Game versions: 24 hours

Cache keys are prefixed with `modrinth:` to avoid conflicts with CurseForge cache.

## Differences from CurseForge

Key differences between Modrinth and CurseForge integration:

1. **Authentication**: Modrinth doesn't require an API key (only User-Agent), while CurseForge requires an API key
2. **IDs**: Modrinth uses string IDs, while CurseForge uses integer IDs
3. **API Version**: Modrinth uses v2 API, CurseForge uses v1 API
4. **Response Format**: Modrinth returns different JSON structures (e.g., `hits` array for search vs CurseForge's `data` array)
5. **Version Filtering**: Modrinth uses `game_versions` and `loaders` query parameters (JSON arrays), while CurseForge uses `gameVersion` and `modLoaderType` (single values)

The service abstracts these differences, providing a consistent interface for the application.

