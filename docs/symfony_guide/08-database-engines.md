# 08 · Motores de base de datos (PostgreSQL y MySQL)

Este repositorio está preparado como **plantilla base** para distintos portales. Puedes elegir el motor de BD con una sola variable en `.env`.

---

## 8.1 · Motores soportados

| Motor | Imagen Docker | Perfil Compose | Puerto por defecto |
| ----- | ------------- | -------------- | ------------------ |
| **PostgreSQL 18** | `postgres:18.3-alpine` | `postgresql` | `5432` |
| **MySQL 8.4 LTS** | `mysql:8.4` | `mysql` | `3306` |

El contenedor PHP incluye extensiones `pdo_pgsql`, `pgsql`, `pdo_mysql` y `mysqli` para soportar ambos motores sin reconstruir al cambiar de portal.

---

## 8.2 · Variables de entorno (fuente única)

Todas viven en `.env` (o `.env.local` en producción):

| Variable | Descripción |
| -------- | ----------- |
| `DATABASE_ENGINE` | `postgresql` o `mysql` — define el esquema de `DATABASE_URL` |
| `COMPOSE_PROFILES` | Debe coincidir con `DATABASE_ENGINE` (Docker Compose arranca el servicio correcto) |
| `DATABASE_NAME` | Nombre de la base de datos |
| `DATABASE_USER` | Usuario de aplicación |
| `DATABASE_PASSWORD` | Contraseña del usuario |
| `DATABASE_ROOT_PASSWORD` | Contraseña root (solo MySQL) |
| `DATABASE_HOST` | Host de conexión (`database` dentro de Docker; alias de red común) |
| `DATABASE_PORT` | Puerto expuesto en el host |
| `DATABASE_SERVER_VERSION` | Versión para Doctrine (`18` / `8.4`) |
| `DATABASE_CHARSET` | `utf8` (PostgreSQL) / `utf8mb4` (MySQL) |
| `DATABASE_URL` | Construida automáticamente a partir de las variables anteriores |

Ejemplo en `.env`:

```dotenv
DATABASE_ENGINE=postgresql
COMPOSE_PROFILES=postgresql

DATABASE_NAME=app_pokedex
DATABASE_USER=app
DATABASE_PASSWORD=app
DATABASE_ROOT_PASSWORD=root
DATABASE_HOST=database
DATABASE_PORT=5432
DATABASE_SERVER_VERSION=18
DATABASE_CHARSET=utf8

DATABASE_URL="${DATABASE_ENGINE}://${DATABASE_USER}:${DATABASE_PASSWORD}@${DATABASE_HOST}:${DATABASE_PORT}/${DATABASE_NAME}?serverVersion=${DATABASE_SERVER_VERSION}&charset=${DATABASE_CHARSET}"
```

---

## 8.3 · Usar PostgreSQL (por defecto)

```dotenv
DATABASE_ENGINE=postgresql
COMPOSE_PROFILES=postgresql
DATABASE_PORT=5432
DATABASE_SERVER_VERSION=18
DATABASE_CHARSET=utf8
```

```bash
docker compose up -d
docker compose ps   # app_db_postgresql + app_php
```

---

## 8.4 · Cambiar a MySQL 8.4 LTS

Edita `.env`:

```dotenv
DATABASE_ENGINE=mysql
COMPOSE_PROFILES=mysql
DATABASE_PORT=3306
DATABASE_SERVER_VERSION=8.4
DATABASE_CHARSET=utf8mb4
```

Reinicia los contenedores:

```bash
docker compose down
docker compose up -d
docker compose ps   # app_db_mysql + app_php
```

> **Importante:** cada motor usa su propio volumen (`db_data_postgresql` / `db_data_mysql`). Los datos no se comparten entre motores.

---

## 8.5 · Cómo funciona Docker Compose

- Servicios: `database-postgresql` (perfil `postgresql`) y `database-mysql` (perfil `mysql`).
- Solo arranca el servicio del perfil activo (`COMPOSE_PROFILES` en `.env`).
- Ambos exponen el alias de red `database`, así `DATABASE_HOST=database` funciona para Symfony en ambos casos.
- El servicio `php` espera al healthcheck del motor activo (`depends_on` con `required: false`).

---

## 8.6 · Acceso directo a la BD

### PostgreSQL

```bash
docker compose exec database-postgresql psql -U app -d app_pokedex -c "\dt"
```

### MySQL

```bash
docker compose exec database-mysql mysql -uapp -papp app_pokedex -e "SHOW TABLES;"
```

Desde el host, conecta a `127.0.0.1` y el `DATABASE_PORT` configurado.

---

## 8.7 · Migraciones por motor (`migrations/postgresql` y `migrations/mysql`)

Doctrine Migrations lee la carpeta según `DATABASE_ENGINE` en `.env`:

```yaml
# config/packages/doctrine_migrations.yaml
'DoctrineMigrations': '%kernel.project_dir%/migrations/%env(DATABASE_ENGINE)%'
```

| Motor | Carpeta | Estado en este repo |
| ----- | ------- | ------------------- |
| `postgresql` | `migrations/postgresql/` | Migraciones de la Pokédex |
| `mysql` | `migrations/mysql/` | Vacía (generar al crear un portal MySQL) |

### Comandos

```bash
# Ver migraciones del motor activo
docker compose exec php php bin/console doctrine:migrations:status

# Crear nueva migración (se guarda en la subcarpeta del motor activo)
docker compose exec php php bin/console make:migration

# Aplicar
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Cambiar de motor

1. Ajusta `DATABASE_ENGINE`, `COMPOSE_PROFILES` y variables de conexión en `.env`.
2. `docker compose down && docker compose up -d`
3. Ejecuta `doctrine:migrations:migrate` — solo aplicará las de la carpeta del motor activo.

Cada motor mantiene su propio historial en la tabla `doctrine_migration_versions` de su base de datos.

> **No copies** migraciones PostgreSQL a `mysql/` sin adaptar el SQL. Genera migraciones MySQL con `make:migration` cuando `DATABASE_ENGINE=mysql`.

---

## 8.8 · Checklist al crear un portal nuevo

1. Copia/clona la plantilla.
2. Define `DATABASE_NAME` (ej. `portal_ventas`).
3. Elige `DATABASE_ENGINE` y ajusta `COMPOSE_PROFILES`, `DATABASE_PORT`, `DATABASE_SERVER_VERSION`, `DATABASE_CHARSET`.
4. `docker compose up -d`
5. `docker compose exec php composer install`
6. `docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction`

---

## 8.9 · Producción

- Sobrescribe credenciales en `.env.local` o variables del orquestador (Kubernetes, ECS, etc.).
- No definas `DATABASE_URL` ni `DATABASE_*` en `compose.yaml` bajo `php.environment`.
- Usa secretos reales para `DATABASE_PASSWORD` y `DATABASE_ROOT_PASSWORD`.
