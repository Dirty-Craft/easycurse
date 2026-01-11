# Dependency Handling System

This project includes a comprehensive dependency handling system that automatically manages mod dependencies and conflicts when adding, removing, or updating mods in mod packs. The system integrates with both CurseForge and Modrinth APIs to fetch dependency information and ensures mod packs remain consistent and functional.

## Overview

The dependency handling system provides:

- **Automatic dependency resolution**: Required dependencies are automatically added when a mod is added to a mod pack
- **Conflict detection**: Incompatibilities between mods are detected and displayed to users
- **Dependency tree visualization**: Users can see the full dependency tree when adding mods
- **Orphaned dependency cleanup**: Automatically removes dependencies that are no longer needed
- **Removal prevention**: Prevents removal of mods that are required by other mods

## Service Class

The `DependencyResolutionService` class (`src/app/Services/DependencyResolutionService.php`) handles all dependency-related operations. It works with the unified `ModService` to fetch dependency information from both CurseForge and Modrinth.

### Key Methods

- `getDependencyTree()` - Recursively builds a complete dependency tree for a mod file/version
- `getRequiredDependencies()` - Returns a flattened list of all required dependencies
- `checkConflicts()` - Identifies conflicts between a new mod and existing mods in a mod pack
- `validateModPackDependencies()` - Validates all dependencies in a mod pack and identifies missing ones

## Dependency Types

The system recognizes four types of dependencies:

1. **Required** - Dependencies that must be present for the mod to function
2. **Optional** - Dependencies that enhance functionality but are not required
3. **Embedded** - Libraries that are typically bundled with the mod
4. **Incompatible** - Mods that conflict with the current mod

Only required dependencies are automatically added to mod packs. Optional and incompatible dependencies are displayed to users for informational purposes.

## How It Works

### Adding Mods

When a user adds a mod to a mod pack:

1. The system fetches the dependency tree for the selected mod version
2. Conflicts with existing mods are detected and displayed
3. The dependency tree is shown to the user before confirmation
4. Upon adding the mod, all required dependencies are automatically added
5. Dependencies are marked as auto-added using the `is_auto_added` flag
6. The process recursively handles sub-dependencies

### Removing Mods

When a user attempts to remove a mod:

1. The system checks if the mod is required by any other mods in the pack
2. If required, removal is prevented and an error message is displayed
3. If not required, the mod is removed
4. The system then checks for orphaned auto-added mods
5. Auto-added mods that are no longer needed by any remaining mod are automatically removed

### Updating Mods

When a user updates a mod version:

1. The system fetches dependencies for the new version
2. Required dependencies for the new version are automatically added
3. Orphaned dependencies from the old version are cleaned up

### Conflict Detection

Conflicts are detected in two directions:

1. **Direct conflicts**: The mod being added declares another mod as incompatible
2. **Reverse conflicts**: An existing mod declares the new mod as incompatible

Both types of conflicts are displayed to the user before adding the mod.

## Database Schema

The `mod_pack_items` table includes a field to track automatically added dependencies:

- `is_auto_added` - Boolean field (default: `false`) that flags mods added automatically as dependencies

### Migration

The field was added via migration `2026_01_11_194647_add_is_auto_added_to_mod_pack_items_table.php`:

```php
$table->boolean('is_auto_added')->default(false)->after('source');
```

## Implementation Details

### Dependency Resolution Service

The `DependencyResolutionService` uses recursion to build dependency trees:

- Tracks visited mods to prevent infinite loops from circular dependencies
- Fetches compatible versions for each dependency based on the mod pack's Minecraft version and loader
- Builds a hierarchical tree structure showing the relationship between mods
- Flattens the tree when needed to get a simple list of dependencies

### Controller Integration

The `ModPackController` integrates dependency handling into mod pack management:

- `addRequiredDependencies()` - Private method that recursively adds required dependencies
- `isModRequiredByOthers()` - Checks if a mod is required by other mods before removal
- `removeOrphanedAutoAddedMods()` - Cleans up dependencies that are no longer needed
- `getModDependencies()` - API endpoint that returns dependency tree and conflicts for a mod

### Frontend Integration

The dependency information is displayed in the "Add Mod" modal:

- Dependency tree is shown when a file/version is selected
- Conflicts are highlighted with warnings
- Users see which dependencies will be automatically added
- Error messages are shown when attempting to remove required mods

## API Endpoints

The following endpoint provides dependency information:

- `GET /mod-packs/{id}/mod-dependencies?mod_id={mod_id}&file_id={file_id}&source={curseforge|modrinth}` - Returns the dependency tree and conflicts for a mod file/version

## Error Handling

The system handles various error scenarios:

- **Missing dependencies**: If a dependency cannot be found or doesn't have a compatible version, it's skipped
- **API failures**: Failed API requests are logged but don't break the dependency resolution process
- **Circular dependencies**: The system prevents infinite loops by tracking visited mods
- **Required mod removal**: Users receive clear error messages when attempting to remove required mods

## User Experience

### Adding Mods

When adding a mod, users see:

- A note explaining that dependencies will be automatically added
- A list of required dependencies that will be added
- Warnings about conflicts with existing mods
- The dependency tree structure

### Removing Mods

When attempting to remove a mod:

- If the mod is required by others, a clear error message is shown listing which mods require it
- If the mod can be removed, it's removed along with any orphaned dependencies
- The system ensures mod packs remain in a valid state

### Bulk Operations

Bulk deletion operations:

- Check all selected mods for required dependencies
- Prevent deletion if any mod is required by mods not being deleted
- Show comprehensive error messages listing all conflicts

## Platform Support

The dependency system works with both CurseForge and Modrinth:

- **CurseForge**: Uses relation types (RequiredDependency, OptionalDependency, Incompatible, EmbeddedLibrary)
- **Modrinth**: Uses dependency types (required, optional, incompatible, embedded)

The `ModService` normalizes these differences, allowing the dependency system to work seamlessly with both platforms.

## Best Practices

When working with the dependency system:

1. Always use `DependencyResolutionService` for dependency operations rather than directly querying APIs
2. Check for conflicts before allowing mod addition
3. Validate dependencies after batch operations
4. Respect the `is_auto_added` flag when determining which mods can be safely removed
5. Handle API failures gracefully, as dependency information may not always be available

