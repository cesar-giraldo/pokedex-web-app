# 07 · Aplicación Pokédex (estado actual del repositorio)

Este capítulo documenta la **aplicación concreta** que vive en este repositorio. Los pasos 01–06 enseñan el stack con una entidad de ejemplo `Producto`; aquí se describe lo que el proyecto implementa hoy.

La aplicación está dividida por **contexto** en PHP (`src/Admin`, `src/Web`, `src/Api`), plantillas Twig (`templates/admin`, `templates/web`), assets (`assets/admin`, `assets/web`) y estáticos (`public/admin`, `public/web`, `public/shared`). Detalle de frontend en [`04-tailwind-y-stimulus.md`](./04-tailwind-y-stimulus.md).

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

`App\Admin\Service\PokeAPI\PokeAPIClient` usa `HttpClientInterface` de Symfony para llamar a <https://pokeapi.co/api/v2/>.

| Método | Endpoint | Retorno |
| ------ | -------- | ------- |
| `listPokemons($limit, $offset)` | `/pokemon/?limit=&offset=` | Lista `{name, url}` |
| `getPokemonByName($name)` | `/pokemon/{name}` | `PokemonDetails` (DTO) |
| `getPokemonTypeByName($name)` | `/type/{name}` | `PokemonTypeDetails` (DTO) |

DTOs en `src/Admin/Service/PokeAPI/PokemonDetails.php` y `PokemonTypeDetails.php`.

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

Implementación: `src/Admin/Command/SearchStorePokemonsCommand.php`.

---

## 7.3 · Controladores y rutas (por contexto)

El código PHP está organizado en tres contextos: `src/Admin/`, `src/Web/` y `src/Api/`. Las plantillas siguen la misma separación en `templates/admin/`, `templates/web/` y (para la API) respuestas JSON sin Twig.

### Web — sitio público

| Clase | Ruta | Descripción |
| ----- | ---- | ----------- |
| `App\Web\Controller\HomeController` | **GET `/`** | Lista Pokémon de la BD. Si la tabla está vacía, inserta un Pikachu de ejemplo. |
| `App\Web\Controller\HomeController` | **GET `/internal-pokemon-search/{name}`** | JSON `{success, data, errors}` para búsqueda en BD local (usado por Stimulus). |

Plantilla: `templates/web/home/index.html.twig` (extiende `@web/base.html.twig`).

Frontend: `importmap('web')`, assets en `assets/web/` y controladores Stimulus en `assets/controllers/web/`.

### Admin — panel y UI kit

| Clase | Prefijo / rutas | Descripción |
| ----- | --------------- | ----------- |
| `App\Admin\Controller\DesignController` | `/design/*` | Kit de UI (formularios, tablas, gráficos, auth mockups, alertas, badges, etc.) |
| `App\Admin\Controller\PokemonController` | `/admin/pokemons` | Listado paginado con búsqueda y ordenación |

Rutas del kit de diseño:

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

Plantillas bajo `templates/admin/` (alias Twig `@admin/…`) y partials en `templates/admin/partials/`.

Frontend: `importmap('admin')`, assets en `assets/admin/` y controladores Stimulus en `assets/controllers/admin/`.

Imágenes estáticas del admin: `public/admin/images/…` referenciadas con `asset('…', 'admin')`.

### Api — servicios JSON

| Clase | Ruta | Descripción |
| ----- | ---- | ----------- |
| `App\Api\Controller\PokemonApiController` | **GET `/api/v1/pokemones`** | Lista de Pokémon serializados (`groups: ['pokemon:read']`) |

Sin assets frontend propios; el prefijo `/api/v1` se define en `config/routes.yaml`.

---

## 7.4 · Frontend interactivo (por contexto)

Ver también la sección **Separación de frontend por contexto** en [`04-tailwind-y-stimulus.md`](./04-tailwind-y-stimulus.md).

### Entrypoints e importmap

| Contexto | Plantilla base | Importmap |
| -------- | -------------- | --------- |
| Web | `templates/web/base.html.twig` | `{{ importmap('web') }}` |
| Admin | `templates/admin/base.html.twig` | `{{ importmap('admin') }}` |

Definidos en `importmap.php` como `assets/web/app.js` y `assets/admin/app.js`.

### Live Components (Symfony UX)

Los componentes PHP viven en `src/Admin/Twig/Components/`; las plantillas de los usados en el sitio público están en `templates/web/components/`.

| Componente PHP | Twig | Contexto | Función |
| -------------- | ---- | -------- | ------- |
| `PokemonInternalSearch` | `templates/web/components/pokemon_internal_search.html.twig` | Web | Busca en BD vía `LiveAction` |
| `PokemonExternalSearch` | `templates/web/components/pokemon_external_search.html.twig` | Web | Busca en PokeAPI desde el servidor |
| `MultiSelectComponent` | `templates/admin/components/multi_select_component.html.twig` | Admin | Selector múltiple reutilizable |

Uso en la home pública (`templates/web/home/index.html.twig`):

```twig
{{ component('pokemon_internal_search', { name: 'Pikachu' }) }}
{{ component('pokemon_external_search', { name: 'Charmander' }) }}
```

### Stimulus

| Controlador | Archivo | Contexto | Uso |
| ----------- | ------- | -------- | --- |
| `hi` | `assets/controllers/web/hi_controller.js` | Web | Demo saludo |
| `search-pokemon` | `assets/controllers/web/search_pokemon_controller.js` | Web | Búsqueda PokeAPI (fetch) + endpoint interno `/internal-pokemon-search/{name}` |
| `template-base` | `assets/controllers/admin/template_base_controller.js` | Admin | Layout sidebar, dark mode, breadcrumb |
| `admin-paginator` | `assets/controllers/admin/admin_paginator_controller.js` | Admin | Paginación en listado de Pokémon |
| `component-multi-select` | `assets/controllers/admin/component_multi_select_controller.js` | Admin | Multi-select del kit UI |
| `template-*-chart` | `assets/controllers/admin/template_*_chart_controller.js` | Admin | Gráficos ApexCharts del kit |
| `csrf-protection` | `assets/controllers/shared/csrf_protection_controller.js` | Shared | Protección CSRF en formularios |

Rutas de descubrimiento configuradas en `config/packages/stimulus.yaml` (`assets/controllers/admin`, `web`, `shared`).

### Tailwind

Dos hojas de estilo compiladas (admin y web), configuradas en `config/packages/symfonycasts_tailwind.yaml`:

- `assets/admin/styles/app.css` → escanea `templates/admin` (`@source`)
- `assets/web/styles/app.css` → escanea `templates/web` (`@source`)

Compilar en desarrollo:

```bash
docker compose exec php php bin/console tailwind:build
docker compose exec php php bin/console tailwind:build --watch
```

### Assets estáticos (`public/`)

| Paquete Symfony | Carpeta | Ejemplo Twig |
| --------------- | ------- | ------------ |
| `admin` | `public/admin/` | `asset('images/logo/logo.svg', 'admin')` |
| `web` | `public/web/` | `asset('images/hero.svg', 'web')` |
| `shared` | `public/shared/` | `asset('images/pokemon/pokeball.png', 'shared')` |

Configuración en `config/packages/framework.yaml` → `framework.assets.packages`.

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
| `HomeController` con listado | `App\Web\Controller\HomeController` — listado público + demos interactivas |
| Panel / CRUD backend | `App\Admin\Controller\PokemonController` — `/admin/pokemons` |
| API REST JSON | `App\Api\Controller\PokemonApiController` — `/api/v1/pokemones` |
| Ejemplo Live Component `PokemonSearch` | `PokemonInternalSearch`, `PokemonExternalSearch` (PHP en `src/Admin/Twig/Components/`, Twig en `templates/web/components/`) |
| `App\Service\PokeAPIClient` (en guía 04) | `App\Admin\Service\PokeAPI\PokeAPIClient` |
| Plantilla `base.html.twig` única | `@web/base.html.twig` (sitio público) y `@admin/base.html.twig` (admin + `/design`) |
| `importmap('app')` | `importmap('web')` en web, `importmap('admin')` en admin |
| `assets/app.js` + `assets/styles/app.css` | `assets/web/` y `assets/admin/` con CSS y entrypoints separados |
| `public/images/` genérico | `public/admin/`, `public/web/`, `public/shared/` con paquetes `asset()` |

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

- <https://localhost> — Pokédex (contexto **Web**)
- <https://localhost/design> — UI kit (contexto **Admin**)
- <https://localhost/admin/pokemons> — Listado administrativo de Pokémon
- <https://localhost/api/v1/pokemones> — API JSON
