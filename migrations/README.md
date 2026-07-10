# Migraciones por motor de base de datos

Doctrine carga automáticamente la carpeta que coincide con `DATABASE_ENGINE` en `.env`:

| `DATABASE_ENGINE` | Carpeta activa |
| ----------------- | -------------- |
| `postgresql` | `migrations/postgresql/` |
| `mysql` | `migrations/mysql/` |

Configuración: `config/packages/doctrine_migrations.yaml`

```yaml
'DoctrineMigrations': '%kernel.project_dir%/migrations/%env(DATABASE_ENGINE)%'
```

## Crear migraciones

Con el motor correcto en `.env`:

```bash
docker compose exec php php bin/console make:migration
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

El archivo se generará en la subcarpeta del motor activo.

## Contenido actual

- **`postgresql/`** — migraciones de este proyecto (Pokédex), generadas para PostgreSQL 18.
- **`mysql/`** — vacía; genera las tuyas al iniciar un portal con `DATABASE_ENGINE=mysql`.

> No mezcles SQL específico de un motor en la carpeta del otro. Cada portal/motor mantiene su propio historial en `doctrine_migration_versions`.
