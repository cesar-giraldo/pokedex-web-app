
Pokedex Web App
===============

Compact Symfony application used as a learning / demo project.

Overview
--------

- PHP / Symfony application with frontend assets in `assets/` and server entry at `public/index.php`.
- Docker compose configuration for local development is provided in the repository root.
- Database migrations are stored under `migrations/` and managed with Doctrine.

Quick start
-----------

Prerequisites
- PHP and Composer (project includes platform requirements in `composer.json`).
- Docker and Docker Compose (optional but recommended for a reproducible environment).

Install dependencies

	composer install

Environment
- Copy or adapt environment files from `.env`, `.env.local`, or `.env.dev` as needed.

Run with Docker

	docker compose up -d

Run locally (without Docker)
- If you have the Symfony CLI: `symfony serve -d`
- Or use PHP built-in server from the project root:

	php -S localhost:8000 -t public

Database and migrations

Create / update schema and run migrations with:

	bin/console doctrine:migrations:migrate

Testing

Run the test suite with the provided phpunit binary:

	bin/phpunit

Frontend / Assets

- Frontend sources are in `assets/` (JS and CSS). The project uses importmap/config in `importmap.php`.
- Build or dev tooling depends on your local workflow; see `assets/` for controllers and styles.

Repository structure (high level)

- [src/Kernel.php](src/Kernel.php) — Symfony kernel and application bootstrapping.
- [config/](config/) — Framework and package configuration.
- [src/Controller/](src/Controller/) — HTTP controllers (e.g. `HomeController.php`).
- [templates/](templates/) — Twig templates (base and pages).
- [public/index.php](public/index.php) — Front controller.
- [bin/console](bin/console) — Symfony console helper.
- [migrations/](migrations/) — Doctrine migrations.
- [tests/](tests/) — PHPUnit tests.
- [docker/](docker/) — Docker related files (Caddyfile, php config).

Notes and references

- This README is a concise companion to the internal guide files in `docs/symfony_guide/`.
- For project-specific commands, configuration options, or development tips, consult the Symfony guide.

Messenger (background jobs)

- This project has Symfony Messenger enabled and the transports are configured in `config/packages/messenger.yaml`.
- The environment sets `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` by default, so a database-backed transport (Doctrine) is used. That is why the migration created the `messenger_messages` table.
- If you prefer Messenger to create the table automatically for local/dev use, set `auto_setup=1` in the DSN or run the Messenger setup command; for production it's recommended to manage the schema via Doctrine migrations.

Useful commands

	# run migrations that include the messenger table
	bin/console doctrine:migrations:migrate

	# run workers
	bin/console messenger:consume async -vv

Options

- Keep the Doctrine transport (DB table) if you want persistent queues.
- To avoid creating a DB table, switch `MESSENGER_TRANSPORT_DSN` to a different transport (e.g. `sync://`, Redis or AMQP).


Next steps you might want
- Add a section for developer-specific env var examples.
- Add explicit asset build commands if you use a frontend bundler.
- Add CI instructions to run tests and linting automatically.
