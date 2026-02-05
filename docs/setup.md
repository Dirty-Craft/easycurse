# Setup Instructions
This project uses **Docker** and **Docker Compose** for the setup process in both local and production environments. So make sure you have both of them installed on your machine.

Then, you can simply set up the project using these commands in the root of the project:

```shell
docker compose up -d
docker compose exec app bash setup.sh
# Re-running this command will not ruin anything, so feel free to do that to get a clean setup anytime (e.g. after pulling someone else's branch) instead of running each command manually
# It simply contains standard Laravel setup commands + some additional stuff
```

In `src/.env`, add CurseForge API key (see the [CurseForge guide](https://support.curseforge.com/en/support/solutions/articles/9000208346) on how to get an API key):

```
CURSEFORGE_API_KEY=...
DONATE_WALLET_ADDRESS=... # For donate page
# Set these when you want advertisement to be shown in the ad box
# Use language-specific variables: AD_<LOCALE>_TEXT and AD_<LOCALE>_LINK
# For example: AD_EN_TEXT, AD_EN_LINK, AD_FA_TEXT, AD_FA_LINK
# The locale is automatically determined from the user's language preference
AD_EN_TEXT=
AD_EN_LINK=
AD_FA_TEXT=
AD_FA_LINK=
```

Now, the portal will be accessible at http://localhost:9091.

## Port Configuration
You can change the default ports using the Docker environment file:

```shell
cp .env.docker .env
```

Then edit `.env` in the root of the project and change the ports:

```
APP_HTTP_PORT=9091
VITE_PORT=5173
...
```

## Production Setup
If you want to set up the project on a production server, you should use a different Docker Compose file:

```shell
docker compose -f docker-compose.prod.yml up -d
docker compose exec app bash setup.sh
```

### Deployer
The project includes a simple HTTP deployer (`docker/deployer.php`) that, when called with the correct secret, runs `git pull` and redeploys the production stack.

1. **Set the secret in `.env`** (copy from `.env.docker` in the root of the project if you don’t have one yet):

   ```shell
   cp .env.docker .env
   ```

   Then edit `.env` in the root and set:

   ```
   DEPLOYER_SECRET=your-secret-key-here
   ```

2. **Run the deployer server** (e.g. on the host or in a separate process):

   ```shell
   $ php -S 0.0.0.0:1234 docker/deployer.php
   ```

   Trigger a deployment by opening: `http://<your-host>:1234/?key=your-secret-key-here`. If the `key` matches `DEPLOYER_SECRET`, the deployer runs and responds with “Deployment successful”; otherwise it returns 401.

3. **Deploy from GitHub**  
   A [GitHub Actions workflow](../.github/workflows/deploy.yml) runs on every **push to the production branch** and calls your deployer as a webhook. To use it, add a **repository secret** in GitHub:

   - **Settings → Secrets and variables → Actions → New repository secret**
   - Name: `DEPLOY_WEBHOOK_URL`
   - Value: the full deployer URL including the key, e.g. `http://your-server:1234/?key=your-secret-key-here`

   When you push to the production branch (e.g. `git push origin production`), the workflow triggers the webhook and your server redeploys.

### Backups
The production setup includes an automated database backup service that runs daily. Backups are stored in the `docker/backup` directory as ZIP files containing:

- The full database dump
- All `logs.txt` files from `docker/virtual` directory (preserving directory structure)

The backup schedule can be configured in your Docker environment file (`.env`):

```shell
cp .env.docker .env
```

Then set the backup schedule (cron format: `MINUTE HOUR * * *`):

```
BACKUP_SCHEDULE=0 2 * * *  # Default: 2 AM daily
```

Backup files are named with timestamps: `backup_YYYYMMDD_HHMMSS.zip`

The backup includes only `logs.txt` files from the `docker/virtual` directory, maintaining the original directory structure (e.g., `docker/virtual/subdir/logs.txt` will be backed up as `virtual/subdir/logs.txt` in the ZIP file).

### Mail (Poste.io)
The production setup includes **Poste.io**, a full mail server with SMTP, IMAP, and webmail. You get:

- **Sending from the app** as `noreply@easycurse.com` (Laravel uses the mail server to send transactional email).
- **Real mailboxes** (e.g. `support@easycurse.com`, `ads@easycurse.com`, `upgrade@easycurse.com`) that can **receive** mail from the internet. Each mailbox has its own password.
- **Webmail** where admins log in with a mailbox address and password to read inbox, reply, and manage mail.

#### First-time setup (Poste.io)

1. Open the mail web UI at `http://<your-host>:<MAIL_WEB_PORT>` (default port `8080`).

2. Complete the **Poste.io first-run wizard**: set the server hostname (e.g. `mail.easycurse.com` for MX/DNS), add your domain (e.g. `easycurse.com`), and create the **admin** account.

3. In the Poste.io **admin panel**, create these mailboxes (or the ones you need), each with its own password:
   - `support@easycurse.com`
   - `ads@easycurse.com`
   - `upgrade@easycurse.com`
   - `noreply@easycurse.com` (used by the app to send mail; can be send-only or a normal mailbox).

4. In **`src/.env`**, set the app’s mail config so it can send as `noreply@`:

   ```
   MAIL_MAILER=smtp
   MAIL_HOST=mail
   MAIL_PORT=587
   MAIL_USERNAME=noreply@easycurse.com
   MAIL_PASSWORD=<password you set for noreply@ in Poste.io>
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS="noreply@easycurse.com"
   MAIL_FROM_NAME="${APP_NAME}"
   ```

   These match `src/.env.example` except you must set `MAIL_PASSWORD`.

5. **DNS (for receiving mail and deliverability):** Point your domain’s **MX** record to the host that runs the mail container (e.g. `mail.easycurse.com`). In Poste.io, use the DNS/SPF/DKIM wizards and add the records they show (SPF, DKIM, and optionally DMARC) to your domain.

#### Ports and Docker env

Copy the Docker env file and adjust ports if needed:

```shell
cp .env.docker .env
```

Relevant variables in `.env`:

```
MAIL_SMTP_PORT=25
MAIL_SUBMISSION_PORT=587
MAIL_SMTPS_PORT=465
MAIL_IMAP_PORT=143
MAIL_IMAPS_PORT=993
MAIL_WEB_PORT=8080
```

Admins use **webmail** at `http://<your-host>:<MAIL_WEB_PORT>` (default `8080`), logging in with e.g. `support@easycurse.com` and that mailbox’s password to read and reply to mail.

## Running Commands
You can run any commands in the container:

```shell
docker compose exec app php artisan make:controller ...
docker compose exec app npm install
```

## Container Management
To stop/restart/rebuild the containers:

```shell
docker compose down
docker compose restart
docker compose build
```

## Directory Structure
The root of the project contains:

- Docker related setup
- Documentation folder
- The actual Laravel source code in `src` directory
