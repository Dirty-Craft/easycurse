# Setup Instructions
This project uses **Docker** and **Docker Compose** for the setup process in both local and production environments. So make sure you have both of them installed on your machine.

Then, you can simply set up the project using these commands in the root of the project:

```shell
$ docker compose up -d
$ docker compose exec app bash setup.sh
# Re-running this command will not ruin anything, so feel free to do that to get a clean setup anytime (e.g. after pulling someone else's branch) instead of running each command manually
# It simply contains standard Laravel setup commands + some additional stuff
```

Now, the portal will be accessible at http://localhost:9090.

## Port Configuration
You can change the default 9090 port using the Docker environment file:

```shell
$ cp .env.docker .env
```

Then edit `.env` in the root of the project and change the APP_HTTP_PORT:

```
APP_HTTP_PORT=9090
APP_HTTPS_PORT=9093
...
```

## Production Setup
If you want to set up the project on a production server, you should use a different Docker Compose file:

```shell
$ docker compose -f docker-compose.prod.yml up -d
$ docker compose exec app bash setup.sh
```

## SSL
If you are deploying this project to a server with a domain, the Docker setup can automatically handle the SSL for you.

In Docker env file:

```shell
$ cp .env.docker .env
```

Set these 2:

```
APP_HTTPS_PORT=443
DOMAIN=...
```

The certificates will automatically be generated and configured on the webserver.

## Running Commands
You can run any commands in the container:

```shell
$ docker compose exec app php artisan make:controller ...

# To run npm commands
$ docker compose exec npm npm install
```

## Container Management
To stop/restart/rebuild the containers:

```shell
$ docker compose down
$ docker compose restart
$ docker compose build
```

## Directory Structure
The root of the project contains:

- Docker related setup
- Documentation folder
- The actual Laravel source code in `src` directory
