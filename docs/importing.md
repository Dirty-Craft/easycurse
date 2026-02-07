# Mod Import Feature

## Overview

The mod import feature allows users to import mods from their local filesystem by uploading a folder containing `.jar` files. The system automatically identifies the mods using file hashes and adds them to the mod pack with proper version matching and dependency resolution.

## How It Works

### 1. File Identification

When a user selects a folder containing mod files:

1. **Hash Calculation**: The system calculates two types of hashes for each `.jar` file:
   - **Modrinth SHA-1**: Standard SHA-1 hash of the file content
   - **CurseForge Murmur2 Fingerprint**: CurseForge-specific fingerprint for file identification

2. **API Lookup**: These hashes are sent to both Modrinth and CurseForge APIs to identify the mods:
   - Modrinth: Uses the `version_files` endpoint with SHA-1 hashes
   - CurseForge: Uses the `fingerprints` endpoint with Murmur2 fingerprints

3. **Results Display**: Identified mods are displayed in a modal where users can select which ones to import.

### 2. Version Matching

When importing selected mods, the system performs intelligent version matching:

1. **Compatibility Check**: For each identified mod, the system searches for versions compatible with:
   - The mod pack's Minecraft version (e.g., 1.21)
   - The mod pack's mod loader (e.g., Fabric, Forge, NeoForge, Quilt)

2. **Latest Compatible Version**: If a compatible version is found, the system uses the latest one, even if it's different from the uploaded file's version.

3. **Example Scenario**:
   - User uploads: `litematica-1.20.1-0.15.0.jar`
   - Mod pack version: Minecraft 1.21 with Fabric
   - System finds: `litematica-1.21.0-0.16.0.jar`
   - Result: The 1.21 version is added to the mod pack

### 3. Dependency Resolution

After adding each mod, the system automatically:

1. **Fetches Dependencies**: Retrieves all required dependencies for the mod
2. **Checks Compatibility**: Ensures dependencies are compatible with the mod pack's version
3. **Adds Dependencies**: Automatically adds missing required dependencies
4. **Recursive Resolution**: Resolves dependencies of dependencies

### 4. Conflict Detection

The system checks for conflicts with existing mods in the pack:

1. **Duplicate Detection**: Skips mods that are already in the mod pack
2. **Conflict Warnings**: Identifies mods that conflict with existing ones
3. **User Feedback**: Provides clear messages about skipped or failed imports

## User Interface

### Import Button

Located in the mod pack details page, next to the "Add Mod" button:

```
[+ Add Mod] [Import Mods]
```

### Import Process

1. **Click "Import Mods"**: Opens a folder selection dialog
2. **Select Folder**: Choose a folder containing `.jar` files
3. **Identification**: System identifies mods (shows loading indicator)
4. **Selection Modal**: Displays identified mods with checkboxes
5. **Import**: Click "Import Selected" to add mods to the pack
6. **Results**: Shows summary of added, skipped, and failed imports

### Results Summary

After import, users see a detailed summary:

```
✅ Successfully added 5 mod(s):
  • Litematica (1.21.0-0.16.0) (+ 2 dependencies)
  • Sodium (1.21.0-0.6.0)
  • Iris Shaders (1.21.0-1.8.0) (+ 1 dependency)
  ...

⚠️ Skipped 2 mod(s):
  • JEI: Already in mod pack
  • REI: Already in mod pack

❌ Failed to import 1 mod(s):
  • OptiFine: No compatible version found for 1.21 (fabric)
```

## API Endpoints

### Identify Mods

**Endpoint**: `POST /mod-packs/{id}/identify-mods`

**Request**:
```json
{
  "modrinth_hashes": ["sha1_hash_1", "sha1_hash_2"],
  "curseforge_fingerprints": [123456789, 987654321]
}
```

**Response**:
```json
{
  "data": {
    "modrinth": {
      "hash1": { "project_id": "...", "version_number": "..." }
    },
    "curseforge": {
      "exactMatches": [...],
      "partialMatches": [...]
    }
  }
}
```

### Import Identified Mods

**Endpoint**: `POST /mod-packs/{id}/import-identified-mods`

**Request**:
```json
{
  "mods": [
    {
      "source": "curseforge",
      "mod_id": 123,
      "file_id": 456,
      "display_name": "Mod Name"
    },
    {
      "source": "modrinth",
      "project_id": "abc123",
      "version_id": "xyz789",
      "name": "Another Mod"
    }
  ]
}
```

**Response**:
```json
{
  "success": true,
  "results": {
    "added": [
      {
        "name": "Mod Name",
        "version": "1.21.0-1.0.0",
        "dependencies_added": 2
      }
    ],
    "skipped": [
      {
        "name": "Existing Mod",
        "reason": "Already in mod pack"
      }
    ],
    "failed": [
      {
        "name": "Incompatible Mod",
        "reason": "No compatible version found for 1.21 (fabric)"
      }
    ]
  }
}
```

## Error Handling

### Common Scenarios

1. **No Compatible Version**:
   - Message: "No compatible version found for {version} ({software})"
   - Action: Mod is not added, user is notified

2. **Already in Pack**:
   - Message: "Already in mod pack"
   - Action: Mod is skipped, no duplicate added

3. **Mod Not Found**:
   - Message: "Mod not found"
   - Action: Mod is not added, user is notified

4. **Missing Mod ID**:
   - Message: "Missing mod ID"
   - Action: Mod is not added, user is notified

5. **Network Errors**:
   - Message: "Failed to import mods. Please try again."
   - Action: Import is cancelled, user can retry

## Technical Implementation

### Backend (PHP/Laravel)

**Controller**: `ModPackController::importIdentifiedMods()`

Key features:
- Validates input data
- Uses `ModService` for API interactions
- Performs version matching via `getModFiles()`
- Adds dependencies via `addRequiredDependencies()`
- Returns detailed results for user feedback

### Frontend (Vue.js)

**Component**: `ModPacks/Show.vue`

Key features:
- File selection using `webkitdirectory` attribute
- Hash calculation using `crypto.subtle.digest()` and `cf-fingerprint` library
- Modal for displaying identified mods
- Checkbox selection for mods to import
- Results display with detailed feedback

## Testing

Test file: `tests/Feature/ModPacks/ModPackImportTest.php`

Covers:
- ✅ Version matching with compatible versions
- ✅ Skipping duplicate mods
- ✅ Handling mods with no compatible versions
- ✅ Authentication requirements
- ✅ Authorization checks

## Future Enhancements

Potential improvements:
1. Batch import progress indicator
2. Import history/logs
3. Undo import functionality
4. Import from ZIP files
5. Import from other mod pack formats (CurseForge, Modrinth)
6. Custom version selection during import
7. Conflict resolution options
