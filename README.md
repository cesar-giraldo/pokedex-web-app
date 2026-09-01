
Pokedex Web App
===============

Symfony 8 application that integrates with [PokeAPI](https://pokeapi.co/) to list, search, and persist Pokémon data. Includes a UI kit under `/design` and Docker-based local development with FrankenPHP and PostgreSQL 18.

Overview
--------

- PHP 8.5 / Symfony 8 with Twig, AssetMapper, Tailwind CSS 4, Stimulus, and Symfony UX (Live Components, Turbo, Icons).
- Pokémon domain: entities `Pokemon` and `PokemonType`, PokeAPI client, console command to sync data, and search UI (Live Components + Stimulus).
- Docker Compose stack: FrankenPHP (`php` service) + **PostgreSQL 18** or **MySQL 8.4 LTS** (selectable via `DATABASE_ENGINE` in `.env`).
- Database migrations under `migrations/{postgresql|mysql}/`, selected automatically by `DATABASE_ENGINE`.
- Internal Symfony guide in [`docs/symfony_guide/`](docs/symfony_guide/README.md); application-specific docs in [`docs/symfony_guide/07-aplicacion-pokedex.md`](docs/symfony_guide/07-aplicacion-pokedex.md).

Quick start (Docker — recommended)
----------------------------------

Prerequisites: Docker Desktop (or Docker Engine + Compose v2). You do **not** need PHP, Composer, or a database server installed on the host.

```bash
# 1. Start containers (php waits until database is healthy)
docker compose up -d

# 2. Verify services are running
docker compose ps

# 3. Install PHP dependencies inside the container
docker compose exec php composer install

# 4. Run migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# 5. (Optional) Seed Pokémon from PokeAPI — dry-run by default
docker compose exec php php bin/console search-store-pokemons 10

# 6. (Optional) Persist to database
docker compose exec php php bin/console search-store-pokemons 10 --write=true

# 7. (Optional) Rebuild Tailwind in watch mode (separate terminal)
docker compose exec php php bin/console tailwind:build --watch
```

Open the app:

- Home (Pokédex): <https://localhost>
- UI kit: <https://localhost/design>

> If you see `service "php" is not running`, run `docker compose up -d` first. `docker compose exec` only works when the container is up.

Quick start (without Docker)
----------------------------

Prerequisites: PHP 8.5+, Composer, and PostgreSQL 18 or MySQL 8.4.

```bash
composer install
cp .env .env.local   # adjust DATABASE_URL to your local Postgres
php bin/console doctrine:migrations:migrate --no-interaction
symfony serve -d     # or: php -S localhost:8000 -t public
```

Environment variables
---------------------

Database settings are defined **once** in `.env`. Docker Compose and Symfony read the same variables.

| Variable | Default (PostgreSQL) | Purpose |
| -------- | -------------------- | ------- |
| `DATABASE_ENGINE` | `postgresql` | Active engine: `postgresql` or `mysql` |
| `COMPOSE_PROFILES` | `postgresql` | Must match `DATABASE_ENGINE` (controls which DB container starts) |
| `DATABASE_NAME` | `app_pokedex` | Database name |
| `DATABASE_USER` | `app` | Application user |
| `DATABASE_PASSWORD` | `app` | Application password |
| `DATABASE_ROOT_PASSWORD` | `root` | MySQL root password (ignored by PostgreSQL) |
| `DATABASE_HOST` | `database` | Docker network alias (same for both engines) |
| `DATABASE_PORT` | `5432` | Host port (`3306` for MySQL) |
| `DATABASE_SERVER_VERSION` | `18` | Doctrine server version (`8.4` for MySQL) |
| `DATABASE_CHARSET` | `utf8` | Charset (`utf8mb4` for MySQL) |
| `DATABASE_URL` | *(auto-built)* | Doctrine DSN — derived from the variables above |
| `APP_ENV` | `dev` | Symfony environment |
| `APP_SECRET` | empty in `.env`, set in `.env.dev` | Session/crypto secret |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Async queue backed by `messenger_messages` table |
| `MAILER_DSN` | `null://null` | Mailer (disabled by default) |
| `AWS_REGION` | `us-east-1` | AWS region for S3 |
| `AWS_ACCESS_KEY_ID` | empty | IAM access key for application S3 user |
| `AWS_SECRET_ACCESS_KEY` | empty | IAM secret key (set in `.env.local`) |
| `AWS_S3_BUCKET` | empty | S3 bucket name for media files |
| `AWS_S3_STORAGE_PREFIX` | `%env(APP_ENV)%` | Environment prefix inside the bucket (`dev`, `prod`, `test`) |

### Switch to MySQL 8.4 LTS

Edit `.env`:

```dotenv
DATABASE_ENGINE=mysql
COMPOSE_PROFILES=mysql
DATABASE_PORT=3306
DATABASE_SERVER_VERSION=8.4
DATABASE_CHARSET=utf8mb4
```

Then restart:

```bash
docker compose down
docker compose up -d
```

Full guide: [`docs/symfony_guide/08-database-engines.md`](docs/symfony_guide/08-database-engines.md).

### Rename the database

Change only `DATABASE_NAME`:

```dotenv
DATABASE_NAME=my_portal_db
```

`DATABASE_URL` updates automatically.

For production, override credentials in `.env.local` (gitignored).

> Do not set `DATABASE_URL` or `DATABASE_*` in `compose.yaml` under `php.environment` — that would override `.env.local`.

### If the database name changed with existing data

1. **New environment:** `docker compose down -v && docker compose up -d`
2. **Keep data (PostgreSQL):** `ALTER DATABASE old_name RENAME TO new_name;` inside the container



Useful commands
---------------

All commands below assume Docker. Prefix with `docker compose exec php` or use the aliases from the [troubleshooting guide](docs/symfony_guide/06-comandos-y-troubleshooting.md#62--trabajar-dentro-del-contenedor).

```bash
# Symfony console
docker compose exec php php bin/console list

# Populate Pokémon (dry-run unless --write=true)
docker compose exec php php bin/console search-store-pokemons [limit] [--write=true]

# Tailwind
docker compose exec php php bin/console tailwind:build
docker compose exec php php bin/console tailwind:build --watch

# Code quality
docker compose exec php composer cs:check
docker compose exec php composer cs:fix
docker compose exec php vendor/bin/phpstan analyse

# Tests
docker compose exec php php bin/phpunit

# Messenger worker
docker compose exec php php bin/console messenger:consume async -vv
```

Testing
-------

```bash
docker compose exec php php bin/phpunit
```

Tests live under `tests/` (e.g. `HomeControllerTest`, `PokeAPIClientTest`).

Frontend / assets
-----------------

- Sources: `assets/` (JS controllers, `app.js`, `stimulus_bootstrap.js`).
- Styles: Tailwind 4 via `symfonycasts/tailwind-bundle`; entry CSS is compiled into `public/`.
- Import map: `importmap.php` (no Webpack/Vite).
- Stimulus controllers: `hi`, `search-pokemon`, design kit controllers under `assets/controllers/`.

Repository structure
--------------------

```
pokedex-web-app/
├── assets/                  # JS, Stimulus controllers, CSS
├── bin/console, bin/phpunit
├── config/                  # Symfony configuration
├── docker/                  # Caddyfile, php.ini
├── docs/symfony_guide/      # Step-by-step Symfony + Docker guide
├── migrations/
│   ├── postgresql/          # Migraciones PostgreSQL (motor por defecto)
│   └── mysql/               # Migraciones MySQL (por portal)
├── public/                  # Front controller (index.php), static assets
├── src/
│   ├── Command/             # search-store-pokemons
│   ├── Controller/          # HomeController, DesignController
│   ├── Entity/              # Pokemon, PokemonType
│   ├── Repository/
│   ├── Service/PokeAPI/     # PokeAPIClient, DTOs
│   └── Twig/Components/     # Live Components (internal/external search, multi-select)
├── templates/
│   ├── home/                # Main Pokédex page
│   ├── design/              # UI kit pages (/design/*)
│   ├── components/          # Live Component Twig templates
│   └── partials/            # Shared layout fragments
├── tests/
├── compose.yaml
├── Dockerfile
└── importmap.php
```

Main routes
-----------

| Route | Name | Description |
| ----- | ---- | ----------- |
| `/` | `app_home` | Pokémon list + search demos (Live Components, Stimulus) |
| `/internal-pokemon-search/{name}` | `app_internal_pokemon_search` | JSON lookup in local DB |
| `/design` | `app_design_index` | UI kit index (forms, charts, auth mockups, etc.) |

See [`07-aplicacion-pokedex.md`](docs/symfony_guide/07-aplicacion-pokedex.md) for full feature documentation.

Messenger (background jobs)
---------------------------

Symfony Messenger is enabled (`config/packages/messenger.yaml`). The default transport uses Doctrine (`MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`), which is why migrations create the `messenger_messages` table.

- Keep the Doctrine transport for persistent queues.
- For dev without a DB table, switch to `sync://` or another transport in `.env.local`.
- For production, prefer managing the schema via migrations (`auto_setup=0`).

Documentation
-------------

| Document | Audience |
| -------- | -------- |
| This README | Quick onboarding for the **current** project |
| [`docs/README.md`](docs/README.md) | Index of all documentation |
| [`docs/symfony_guide/`](docs/symfony_guide/README.md) | Tutorial: build the stack from scratch (uses `Producto` as a learning example) |
| [`docs/symfony_guide/07-aplicacion-pokedex.md`](docs/symfony_guide/07-aplicacion-pokedex.md) | **This app's** domain, commands, and UI features |
| [`docs/symfony_guide/08-database-engines.md`](docs/symfony_guide/08-database-engines.md) | PostgreSQL vs MySQL, `DATABASE_ENGINE`, `DATABASE_*` variables |

> The Symfony guide (steps 01–06) teaches the stack with a generic `Producto` entity. The running application uses `Pokemon` / `PokemonType` and PokeAPI integration — see step 07 for the mapping.
