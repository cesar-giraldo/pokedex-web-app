
Pokedex Web App
===============

Symfony 8 application that integrates with [PokeAPI](https://pokeapi.co/) to list, search, and persist Pokémon data. Includes a UI kit under `/design` and Docker-based local development with FrankenPHP and PostgreSQL 18.

Overview
--------

- PHP 8.4 / Symfony 8 with Twig, AssetMapper, Tailwind CSS 4, Stimulus, and Symfony UX (Live Components, Turbo, Icons).
- Pokémon domain: entities `Pokemon` and `PokemonType`, PokeAPI client, console command to sync data, and search UI (Live Components + Stimulus).
- Docker Compose stack: FrankenPHP (`php` service) + PostgreSQL 18 (`database` service).
- Database migrations under `migrations/`, managed with Doctrine.
- Internal Symfony guide in [`docs/symfony_guide/`](docs/symfony_guide/README.md); application-specific docs in [`docs/symfony_guide/07-aplicacion-pokedex.md`](docs/symfony_guide/07-aplicacion-pokedex.md).

Quick start (Docker — recommended)
----------------------------------

Prerequisites: Docker Desktop (or Docker Engine + Compose v2). You do **not** need PHP, Composer, or PostgreSQL installed on the host.

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

Prerequisites: PHP 8.4+, Composer, and a PostgreSQL 18 instance.

```bash
composer install
cp .env .env.local   # adjust DATABASE_URL to your local Postgres
php bin/console doctrine:migrations:migrate --no-interaction
symfony serve -d     # or: php -S localhost:8000 -t public
```

Environment variables
---------------------

| Variable | Default (`.env`) | Purpose |
| -------- | ---------------- | ------- |
| `APP_ENV` | `dev` | Symfony environment |
| `APP_SECRET` | empty in `.env`, set in `.env.dev` | Session/crypto secret |
| `DATABASE_URL` | `postgresql://app:app@database:5432/app?serverVersion=18&charset=utf8` | Doctrine connection (host `database` inside Docker, `127.0.0.1:5432` from host) |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Async queue backed by `messenger_messages` table |
| `MAILER_DSN` | `null://null` | Mailer (disabled by default) |

Override values in `.env.local` (gitignored). Inside Docker, do not set `DATABASE_URL` in `compose.yaml` — Symfony reads it from `.env` / `.env.local` so local overrides work.

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
├── migrations/              # Doctrine migrations (pokemon, pokemon_type, messenger_messages)
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

> The Symfony guide (steps 01–06) teaches the stack with a generic `Producto` entity. The running application uses `Pokemon` / `PokemonType` and PokeAPI integration — see step 07 for the mapping.
