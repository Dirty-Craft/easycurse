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
