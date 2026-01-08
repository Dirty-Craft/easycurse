# EasyCurse
This repository contains the codebase for EasyCurse project licensed under [MIT](LICENSE).

## Get Started
To run the project quickly, run these commands:

```shell
$ docker compose up -d
$ docker compose exec app bash setup.sh
```

In `src/.env`, add CurseForge API key:

```
CURSEFORGE_API_KEY=...
```

Then the project should be accessible in http://localhost:9091.

Read the full setup guide and technical documentation [here](docs/README.md).

## How It Works

EasyCurse is a mod management platform that helps Minecraft players build and manage their own personal modpacks. Unlike the CurseForge App which is designed for installing published modpacks, EasyCurse empowers you to create custom modpacks with mods from both CurseForge and Modrinth, automatically resolve version compatibility, and download everything as ready-to-use ZIP files.

**Three Simple Steps:**

1. **Create Your Mod Pack** - Build unlimited mod packs with your favorite mods from CurseForge and Modrinth. Organize them for different playstyles, servers, or Minecraft versions.

2. **Update Minecraft Version** - When a new Minecraft version releases, simply change the version in your mod pack. EasyCurse automatically finds compatible mod versions from both CurseForge and Modrinth, eliminating manual searching and version checking.

3. **Download & Play** - Get your updated mod pack as a single ZIP file. Extract and play—no launcher dependency, no hassle.

**Key Features:**
- Automatic version matching from CurseForge and Modrinth APIs
- Cloud storage for all your mod packs
- One-click download as ready-to-use ZIP files
- Share mod packs with friends and the community via unique links
- Email notifications when new compatible versions are available
- Update entire mod packs in seconds when Minecraft version changes

Watch the demo video: [demo.mp4](src/public/demo.mp4)
