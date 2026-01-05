# Easy Curse
This repository contains the codebase for Easy Curse project licensed under [MIT](LICENSE).

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

EasyCurse is a mod management companion for Minecraft that eliminates the hassle of manually updating mods when new Minecraft versions are released. Unlike the CurseForge App, which is designed for installing published modpacks, EasyCurse empowers you to build and manage your own personal modpacks with full control.

### Demo

<video src="src/public/demo.mp4" controls width="100%">
  Your browser does not support the video tag.
</video>

### The Problem

When a new Minecraft version drops, manually updating mods is time-consuming and error-prone. You need to:
- Search through hundreds of mod pages
- Check compatibility for each mod
- Download and organize files manually
- Deal with version conflicts

The CurseForge App doesn't help with personal modpacks—it depends on modpack authors to publish updates, leaving you waiting or doing the work yourself.

### The Solution

EasyCurse connects directly to CurseForge's API to automate the entire process:

1. **Create Your Mod Pack**: Log in and create a mod pack with all your favorite CurseForge mods. Organize them however you like and save multiple sets for different playstyles.

2. **Change Minecraft Version**: When a new Minecraft version releases, simply update the version in your mod pack. EasyCurse automatically finds compatible versions for all your mods from CurseForge.

3. **Download & Play**: Get your updated mod pack as a ready-to-use ZIP file. Extract and play—no manual searching, no version checking, no hassle.

### Key Features

- **Automatic Version Matching**: Automatically finds compatible mod versions from CurseForge when you update Minecraft versions
- **One-Click Updates**: Update entire mod packs in seconds with a single click
- **Cloud Storage**: All your mod packs are saved in the cloud, accessible from any device
- **One-Click Download**: Download your entire mod pack as a single ZIP file, ready to extract and play
- **Share Mod Packs**: Share your mod packs with friends and the community via unique shareable links
- **Unlimited Mod Packs**: Create unlimited mod packs for different playstyles, servers, or versions

### Who It's For

EasyCurse is perfect for:
- Minecraft players who want to keep their personal mod collections up to date
- Server administrators managing modded servers
- Content creators building modpacks for their communities
- Anyone who wants full control over their modpacks without waiting for authors to publish updates
