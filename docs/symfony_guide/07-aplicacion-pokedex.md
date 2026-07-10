# 07 · Aplicación Pokédex (estado actual del repositorio)

Este capítulo documenta la **aplicación concreta** que vive en este repositorio. Los pasos 01–06 enseñan el stack con una entidad de ejemplo `Producto`; aquí se describe lo que el proyecto implementa hoy.

---

## 7.1 · Dominio: Pokémon y tipos

### Entidades

| Clase | Tabla | Descripción |
| ----- | ----- | ----------- |
| `App\Entity\Pokemon` | `pokemon` | Nombre, stats (HP, attack, defense, speed), sprites, altura/peso, relación con tipo |
| `App\Entity\PokemonType` | `pokemon_type` | Nombre del tipo, generación, sprite |

Relación: `Pokemon` → `ManyToOne` → `PokemonType`.

Repositorios: `PokemonRepository`, `PokemonTypeRepository` (incluyen `findOneByName()`).

### Migraciones

Ubicación: `migrations/postgresql/` (Doctrine las carga cuando `DATABASE_ENGINE=postgresql`).

| Archivo | Contenido |
| ------- | --------- |
| `Version20260509025445` | Crea `pokemon`, `pokemon_type`, `messenger_messages` |
| `Version20260509215639` | Ajustes adicionales de esquema |
| `Version20260523035351` | `height` como `INT`, `list_order` y `type_id` nullable |

Ejecutar:

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Para MySQL u otros motores, ver [`08-database-engines.md`](./08-database-engines.md#87--migraciones-por-motor-migrationspostgresql-y-migrationsmysql).

---

## 7.2 · Integración con PokeAPI

### Servicio

`App\Service\PokeAPI\PokeAPIClient` usa `HttpClientInterface` de Symfony para llamar a <https://pokeapi.co/api/v2/>.

| Método | Endpoint | Retorno |
| ------ | -------- | ------- |
| `listPokemons($limit, $offset)` | `/pokemon/?limit=&offset=` | Lista `{name, url}` |
| `getPokemonByName($name)` | `/pokemon/{name}` | `PokemonDetails` (DTO) |
| `getPokemonTypeByName($name)` | `/type/{name}` | `PokemonTypeDetails` (DTO) |

DTOs en `src/Service/PokeAPI/PokemonDetails.php` y `PokemonTypeDetails.php`.

### Comando de consola: `search-store-pokemons`

Sincroniza Pokémon desde la API hacia la base de datos.

```bash
# Dry-run (por defecto): muestra qué haría sin escribir en BD
docker compose exec php php bin/console search-store-pokemons 5

# Persistir en base de datos
docker compose exec php php bin/console search-store-pokemons 10 --write=true
```

| Argumento / opción | Default | Descripción |
| ------------------ | ------- | ----------- |
| `limit` | `5` | Cantidad de Pokémon a pedir a la API |
| `--write` / `-w` | `false` | `true` para guardar; cualquier otro valor = dry-run |

Comportamiento:

1. Usa el conteo actual en BD como `offset` (paginación incremental).
2. Por cada Pokémon: obtiene detalle, resuelve/crea el `PokemonType`, persiste `Pokemon`.
3. Omite duplicados por nombre.

Implementación: `src/Command/SearchStorePokemonsCommand.php`.

---

## 7.3 · Controladores y rutas

### `HomeController` (`/`)

- **GET `/`** — Lista Pokémon de la BD. Si la tabla está vacía, inserta un Pikachu de ejemplo.
- **GET `/internal-pokemon-search/{name}`** — JSON con `{success, data, errors}` para búsqueda en BD local.

Plantilla: `templates/home/index.html.twig` (extiende `base-old.html.twig`).

### `DesignController` (`/design/*`)

Kit de UI con páginas de demostración (formularios, tablas, gráficos, auth mockups, alertas, badges, etc.).

| Ruta | Descripción |
| ---- | ----------- |
| `/design` | Índice del kit |
| `/design/form` | Formularios |
| `/design/tables` | Tablas |
| `/design/profile` | Perfil de usuario |
| `/design/charts/*` | Gráficos (line, bar, pie) |
| `/design/ui-elements/*` | Alerts, badges, buttons, images, videos |
| `/design/auth/sign-in`, `sign-up` | Pantallas de autenticación (solo diseño) |
| `/design/page-not-found`, `server-error` | Páginas 404/500 de ejemplo |

Plantillas bajo `templates/design/` y partials en `templates/partials/`.

---

## 7.4 · Frontend interactivo

### Live Components (Symfony UX)

| Componente PHP | Twig | Función |
| -------------- | ---- | ------- |
| `PokemonInternalSearch` | `components/pokemon_internal_search.html.twig` | Busca en BD vía `LiveAction` |
| `PokemonExternalSearch` | `components/pokemon_external_search.html.twig` | Busca en PokeAPI desde el servidor |
| `MultiSelectComponent` | `components/multi_select_component.html.twig` | Selector múltiple reutilizable |

Uso en home:

```twig
{{ component('pokemon_internal_search', { name: 'Pikachu' }) }}
{{ component('pokemon_external_search', { name: 'Charmander' }) }}
```

### Stimulus

| Controlador | Archivo | Uso |
| ----------- | ------- | --- |
| `hi` | `assets/controllers/hi_controller.js` | Demo saludo |
| `search-pokemon` | `assets/controllers/search_pokemon_controller.js` | Búsqueda PokeAPI (fetch) + endpoint interno `/internal-pokemon-search/{name}` |

### Tailwind

Compilar estilos:

```bash
docker compose exec php php bin/console tailwind:build
docker compose exec php php bin/console tailwind:build --watch
```

---

## 7.5 · Calidad de código

| Herramienta | Comando |
| ----------- | ------- |
| PHP CS Fixer | `docker compose exec php composer cs:check` / `cs:fix` |
| PHPStan (nivel 8) | `docker compose exec php vendor/bin/phpstan analyse` |
| PHPUnit | `docker compose exec php php bin/phpunit` |

Configuración: `.php-cs-fixer.dist.php`, `phpstan.dist.neon`, `phpunit.dist.xml`.

---

## 7.6 · Mapeo tutorial → aplicación real

| Concepto en pasos 01–06 | Equivalente en este repo |
| ----------------------- | ------------------------ |
| Entidad `Producto` | `Pokemon` + `PokemonType` |
| `HomeController` con listado | `HomeController` con listado de Pokémon + demos |
| Ejemplo Live Component `PokemonSearch` | `PokemonInternalSearch`, `PokemonExternalSearch` |
| `App\Service\PokeAPIClient` (en guía 04) | `App\Service\PokeAPI\PokeAPIClient` (namespace con subcarpeta) |
| Plantilla `base.html.twig` | Home usa `base-old.html.twig`; kit `/design` usa `base.html.twig` |

---

## 7.7 · Arranque completo (checklist)

```bash
docker compose up -d
docker compose ps                                    # php + database (healthy)
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console search-store-pokemons 10 --write=true   # opcional
docker compose exec php php bin/console tailwind:build --watch                  # terminal aparte
```

Abrir:

- <https://localhost> — Pokédex
- <https://localhost/design> — UI kit
