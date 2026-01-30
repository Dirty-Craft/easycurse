# Runners Feature

The runners feature enables automated execution of Minecraft server runs to validate mod packs. When a user creates a run, the system downloads all mods and mod loader files, then executes the server in an isolated Docker container to ensure the mod pack is functional.

## Overview

The runners system provides:

- **Automated server execution**: Runs Minecraft servers in isolated Docker containers
- **Mod pack validation**: Verifies that mod packs can start successfully
- **Log monitoring**: Tracks server initialization and completion
- **Resource cleanup**: Automatically stops containers when servers finish initializing and deletes all files except `logs.txt`
- **User-initiated stop**: The stop button in the UI signals the runner to stop the container (not just the database); the app writes `runner.stop` and the runner stops the container within about a second

## Architecture

The system consists of three main components:

1. **Laravel Application**: Creates run directories, downloads files, and signals readiness
2. **Runner Script**: Monitors for run requests and manages container execution
3. **Docker-in-Docker Container**: Provides isolated execution environment for Minecraft servers

## Run Creation Process

When a user creates a run via `POST /mod-packs/{id}/runs`:

1. A `ModPackRun` record is created with `is_completed = false`
2. A directory structure is created at `/shared/virtual/{run_id}/`
3. Mod loader files are downloaded from ServerJars API
4. Vanilla server JAR is downloaded (for Forge/NeoForge)
5. All mod files are downloaded to the `mods/` directory
6. Server configuration files are written (`eula.txt`, `run.sh`)
7. A `runner.pick` file is created to signal the runner script

## Runner Script

The `runner.sh` script (`docker/virtual/runner.sh`) runs continuously in the `virtual` Docker container:

- Polls for `runner.pick` files every second
- Before starting a run, checks for `runner.stop` in that run directory; if present, removes both files and skips starting (user stopped before the run was picked)
- When a run is picked, removes the pick file and starts a server container
- Monitors server logs for completion message `[Server thread/INFO]: Done`
- **User-requested stop**: Each second the monitor also checks for `runner.stop` in the run directory; if the app wrote this file (user clicked Stop), the runner runs `docker stop` on the container and cleans up
- Automatically stops the container after server initialization completes
- Handles timeouts (5 minutes) if completion message is not detected
- Cleans up run directory after completion, deleting all files except `logs.txt`

### Container Execution

Each run executes in a separate Docker container:

- **Image**: `eclipse-temurin:21-jdk`
- **Working Directory**: `/workspace` (mounted from run directory)
- **Command**: Executes `run.sh` script generated for the mod pack
- **Logs**: Output redirected to `logs.txt` in the run directory

## Mod Loader Support

The system supports multiple mod loaders with different execution strategies:

### Fabric/Quilt
- Downloads installer JAR from ServerJars
- `run.sh` executes installer to generate server launcher
- Installer downloads Minecraft server JAR automatically
- Runs generated launcher (`fabric-server-launch.jar` or `quilt-server-launch.jar`)

### Forge/NeoForge
- Downloads installer JAR from ServerJars
- Downloads vanilla Minecraft server JAR separately
- `run.sh` executes installer with `--installServer` flag
- Runs generated server files or falls back to `server.jar`

## Database Schema

The `mod_pack_runs` table tracks run execution:

- `id` - Primary key
- `mod_pack_id` - Foreign key to `mod_packs` table
- `is_completed` - Boolean flag indicating run completion status
- `created_at` / `updated_at` - Timestamps

## API Endpoints

The following endpoints manage runs:

- `POST /mod-packs/{id}/runs` - Create a new run
- `POST /mod-packs/{id}/runs/{runId}/stop` - Stop a run: marks it completed in the database and writes `runner.stop` so the virtual runner stops the container (when the run directory exists)
- `GET /mod-packs/{id}/runs` - Get run history for a mod pack
- `GET /mod-packs/{id}/runs/{runId}/logs` - Retrieve server logs for a run

## File Structure

Each run creates the following directory structure during execution:

```
/shared/virtual/{run_id}/
├── mods/
│   └── {mod_files}.jar
├── eula.txt
├── run.sh
├── runner.pick (created when ready, removed before execution)
├── runner.stop (created by app when user clicks Stop; runner removes it when stopping the container)
├── logs.txt (generated during execution)
├── {loader}-installer.jar (Fabric/Quilt/Forge/NeoForge)
├── {loader}-installer-info.txt
├── server.jar (vanilla or generated)
└── {loader}-server-launch.jar (Fabric/Quilt, generated)
```

**After run completion**, the runner script automatically deletes all files and subdirectories except `logs.txt`, leaving only:

```
/shared/virtual/{run_id}/
└── logs.txt
```

## Run Limits

Free users are limited to 10 runs per month. Premium users have unlimited runs. The limit is enforced in `ModPackController::createRun()` by checking `User::getMonthlyRunCount()`.

## Log Monitoring

The runner script monitors `logs.txt` for the completion message:

```
[Server thread/INFO]: Done
```

When detected, the container is automatically stopped. If the message is not found within 5 minutes, the container is stopped due to timeout.

**User-requested stop**: When the user clicks Stop in the UI, the app marks the run completed and writes `runner.stop` in the run directory. The runner’s monitor loop checks for this file every second and, when present, runs `docker stop` on the container and cleans up. The container typically stops within about a second.

## Error Handling

The system handles various error scenarios:

- **Failed downloads**: Logged but don't prevent run creation if critical files succeed
- **Missing directories**: Throws `RuntimeException` with clear error messages
- **Installation failures**: Logged in `logs.txt` and container exits with error code
- **Timeout**: Container stopped after 5 minutes if completion not detected

## Docker Configuration

The `virtual` service in `docker-compose.yml`:

- Uses `docker:dind` image (Docker-in-Docker)
- Runs with `privileged: true` to enable Docker daemon
- Mounts `/shared/virtual` volume for run directories
- Executes `runner.sh` on startup

## Implementation Details

### Run Directory Creation

The `ModPackController::createRun()` method:

1. Validates base directory exists and is writable
2. Creates run-specific directory structure
3. Downloads mod loader via `downloadModLoaderFromServerJars()`
4. Downloads vanilla server JAR for Forge/NeoForge
5. Generates `run.sh` script based on mod loader type
6. Downloads all mod files from mod pack items
7. Creates `runner.pick` file to trigger execution

### Runner Script Execution

The `runner.sh` script:

- Runs in an infinite loop checking for `runner.pick` files
- Before starting a run, skips if `runner.stop` exists (user already stopped)
- Uses `find` to locate pick files across all run directories
- Starts server containers in background
- Spawns monitoring processes to watch logs; each iteration checks for `runner.stop` and stops the container if present
- Handles cleanup on script termination (SIGINT/SIGTERM)

### Container Lifecycle

1. Container starts with `docker run` command
2. Executes `run.sh` which starts Minecraft server
3. Server logs written to `logs.txt`
4. Monitoring process watches for completion and for `runner.stop` (user-requested stop)
5. Container stopped when done, timeout reached, or user requested stop
6. Run directory cleaned up: all files and subdirectories deleted except `logs.txt`

### Stop Run (User-requested)

`ModPackController::stopRun()`:

1. Authorizes and loads the run, then sets `is_completed = true`
2. If the run directory `/shared/virtual/{runId}` exists, writes `runner.stop` there so the virtual runner (which shares the volume) stops the container
3. Returns JSON success; the runner typically stops the container within about a second

## Best Practices

When working with the runners system:

1. Always check `is_completed` status before considering a run finished
2. Handle timeout scenarios gracefully in the frontend
3. Disk space usage is minimized as run directories are automatically cleaned up after completion (only `logs.txt` remains)
4. Ensure Docker daemon is running in the `virtual` container
5. Verify volume mounts are correctly configured in docker-compose

