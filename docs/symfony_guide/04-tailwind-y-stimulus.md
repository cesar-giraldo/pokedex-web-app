# 04 · Tailwind CSS 4 + StimulusBundle

En esta sección instalaremos:

- **TailwindBundle 0.12+** (de SymfonyCasts) → descarga el binario standalone de Tailwind 4 sin necesidad de Node.js para compilar.
- **StimulusBundle** → ya está incluido en `webapp-pack`, solo lo configuraremos.

> **¿Por qué Tailwind 4 standalone?** Tailwind 4 trae un compilador nativo en Rust llamado **Oxide** que es ~10× más rápido que la versión 3. El binario funciona sin Node, y `TailwindBundle` lo descarga automáticamente.

---

## Paso 4.1 · Verificar que AssetMapper está instalado

`AssetMapper` ya viene con `webapp-pack`. Verifícalo:

```bash
docker compose exec php php bin/console debug:asset-map
```

Deberías ver una lista de assets registrados (`app.js`, `styles/app.css`, etc.).

> **AssetMapper** es la nueva forma oficial (Symfony 6.3+) de manejar JS/CSS sin Webpack ni Node. Usa `importmap` nativo del navegador y solo copia archivos a `public/assets/` con un hash para cache busting.

## Paso 4.2 · Instalar TailwindBundle

🐳 Dentro del contenedor:

```bash
docker compose exec php composer require symfonycasts/tailwind-bundle
```

Esto instala el bundle. La recipe crea automáticamente:

- `config/packages/symfonycasts_tailwind.yaml`

## Paso 4.3 · Configurar TailwindBundle para usar Tailwind 4

Edita `config/packages/symfonycasts_tailwind.yaml`:

```yaml
symfonycasts_tailwind:
    binary_version: 'v4.1.14'  # Última versión 4.x al momento de escribir
    # Si quieres siempre la última, puedes omitir esta línea (descarga la "latest")
```

> **Tip:** Consulta versiones disponibles en <https://github.com/tailwindlabs/tailwindcss/releases>. El bundle descargará el binario standalone para tu plataforma (Linux x64 dentro del contenedor).

## Paso 4.4 · Inicializar Tailwind

🐳 Ejecuta el comando de inicialización:

```bash
docker compose exec php php bin/console tailwind:init
```

Este comando hace tres cosas:

1. Descarga el binario de Tailwind 4 a `var/tailwind/`.
2. Modifica `assets/styles/app.css` para que importe Tailwind.
3. Registra el CSS compilado en `templates/base.html.twig`.

El archivo `assets/styles/app.css` quedará así:

```css
@import "tailwindcss";
```

> **Diferencia con Tailwind 3:** En la v4 ya no se usa `@tailwind base; @tailwind components; @tailwind utilities;`. Es solo una línea. Tampoco hay `tailwind.config.js` por defecto (la detección de archivos es automática).

## Paso 4.5 · Verificar el `<link>` en la plantilla base

Revisa `templates/base.html.twig`. Dentro de `<head>` deberías ver algo como:

```twig
<head>
    <meta charset="UTF-8">
    <title>{% block title %}Welcome!{% endblock %}</title>
    {% block stylesheets %}{% endblock %}

    {% block javascripts %}
        {% block importmap %}{{ importmap('app') }}{% endblock %}
    {% endblock %}
</head>
```

Y en `assets/app.js`:

```js
import './styles/app.css';
import './bootstrap.js';
```

Esto significa que cualquier clase de Tailwind que uses en tus plantillas Twig será detectada automáticamente.

## Paso 4.6 · Compilar Tailwind en modo watch (desarrollo)

Necesitas un proceso que escuche cambios en tus archivos `.twig` y `.css` y recompile el CSS. Abre **otra terminal** y ejecuta:

```bash
docker compose exec php php bin/console tailwind:build --watch
```

Verás algo como:

```text
Done in 235ms.
Watching for changes...
```

> **Importante:** Mantén esta terminal abierta mientras desarrolles. Cada vez que guardes un archivo Twig, Tailwind regenerará el CSS.

## Paso 4.7 · Probar Tailwind en una plantilla

Edita `templates/home/index.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Productos{% endblock %}

{% block body %}
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-8">
        <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-4xl font-bold text-indigo-700 mb-4">
                Listado de productos
            </h1>
            <ul class="divide-y divide-gray-200">
                {% for producto in productos %}
                    <li class="py-3 flex justify-between">
                        <span class="font-medium text-gray-800">{{ producto.nombre }}</span>
                        <span class="text-indigo-600 font-semibold">${{ producto.precio }}</span>
                    </li>
                {% else %}
                    <li class="py-3 text-gray-500">No hay productos.</li>
                {% endfor %}
            </ul>
        </div>
    </div>
{% endblock %}
```

Recarga <https://localhost> y verás los estilos de Tailwind aplicados. 🎨

## Paso 4.8 · Configuración avanzada de Tailwind 4 (opcional)

Si necesitas personalizar colores, fuentes o variables de tema, ya **no se hace en `tailwind.config.js`**. En Tailwind 4 se hace directamente en CSS con la directiva `@theme`:

`assets/styles/app.css`:

```css
@import "tailwindcss";

@theme {
    --color-primary: oklch(0.55 0.2 260);
    --color-primary-fg: oklch(0.98 0.01 260);
    --font-display: "Inter", "system-ui", "sans-serif";
}

/* Componente personalizado */
@layer components {
    .btn-primary {
        @apply px-4 py-2 rounded-lg bg-primary text-primary-fg
               hover:opacity-90 transition;
    }
}
```

> **Tip:** Tailwind 4 usa el espacio de color **oklch**, que es perceptualmente uniforme y produce gradientes más naturales.

---

## Stimulus: hacer la página interactiva

**Stimulus** es un framework JS minimalista creado por Basecamp. Funciona muy bien con Symfony porque no requiere construir SPAs ni archivos enormes: cada controlador es un pequeño archivo JS asociado a un atributo HTML.

### Paso 4.9 · Verificar que StimulusBundle ya está instalado

`webapp-pack` ya incluyó `symfony/stimulus-bundle`. Confírmalo:

```bash
docker compose exec php php bin/console debug:container | grep -i stimulus
```

Si quieres instalarlo manualmente (en otro proyecto):

```bash
composer require symfony/stimulus-bundle
```

### Paso 4.10 · Crear tu primer controlador Stimulus

🐳 Genera un controlador Stimulus llamado `hello`:

```bash
docker compose exec php php bin/console make:stimulus-controller hello
```

Esto crea `assets/controllers/hello_controller.js`:

```js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['name', 'output'];

    greet() {
        this.outputTarget.textContent = `¡Hola, ${this.nameTarget.value}!`;
    }
}
```

### Paso 4.11 · Usar el controlador en una plantilla

Añade un widget interactivo a `templates/home/index.html.twig` justo antes del `</div>` final:

```twig
<div class="mt-8 p-6 bg-amber-50 rounded-xl border border-amber-200"
     {{ stimulus_controller('hello') }}>

    <input type="text"
           {{ stimulus_target('hello', 'name') }}
           placeholder="Tu nombre"
           class="border rounded px-3 py-2 mr-2">

    <button type="button"
            {{ stimulus_action('hello', 'greet') }}
            class="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600">
        Saludar
    </button>

    <p class="mt-3 text-lg font-medium" {{ stimulus_target('hello', 'output') }}></p>
</div>
```

Recarga la página, escribe tu nombre y haz clic en **Saludar**. Verás el saludo aparecer sin recargar la página. 🎉

> **Funciones Twig de Stimulus:**
>
> - `{{ stimulus_controller('hello') }}` → genera `data-controller="hello"`
> - `{{ stimulus_action('hello', 'greet') }}` → genera `data-action="hello#greet"` (asume `click` por defecto)
> - `{{ stimulus_target('hello', 'name') }}` → genera `data-hello-target="name"`

### Paso 4.12 · UX Components (opcional, muy recomendado)

Symfony UX trae componentes JS pre-empaquetados que se integran con Stimulus. Algunos populares:

```bash
# Live Components (componentes Twig que se actualizan vía AJAX)
docker compose exec php composer require symfony/ux-live-component

# Turbo (navegación instantánea sin recargar)
docker compose exec php composer require symfony/ux-turbo

# Iconos (con soporte para Heroicons, Lucide, etc.)
docker compose exec php composer require symfony/ux-icons
```

Lista completa: <https://ux.symfony.com/>

---

### 4.12.1 · Usar `symfony/ux-live-component` para peticiones AJAX

A continuación hay dos flujos comunes y paso a paso usando `symfony/ux-live-component`:

- Petición a un **controlador interno** (lógica en tu app Symfony).
- Petición a una **API externa** (por ejemplo `https://pokeapi.co/api/v2/pokemon/{name}`) donde el componente hará la llamada desde el servidor y actualizará la vista.

Nota: instala el paquete si no lo hiciste aún:

```bash
docker compose exec php composer require symfony/ux-live-component
```

#### A. Petición a un controlador interno (Live Component que llama a un endpoint interno)

1. Crear el Live Component PHP (ejemplo `src/Twig/Components/PokemonSearch.php`). Usa los atributos proporcionados por el paquete para declarar `props` y `actions`.

```php
<?php

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

#[AsLiveComponent()]
final class PokemonSearch
{
    use DefaultActionTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
    }

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public Pokemon|null $pokemon = null;


    #[LiveAction]
    public function search(): void
    {
        $result = $this->em->getRepository(Pokemon::class)->findOneByName($this->name);
        if ($result instanceof Pokemon) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
```

2. Crear la plantilla Twig del componente (por ejemplo `templates/components/PokemonSearch.html.twig`):

```twig
{# templates/components/PokemonSearch.html.twig #}
<div {{attributes}}>
    <input type="text" 
        class="border rounded px-3 py-2 mr-2"
        placeholder="Nombre del Pokemon"
        data-model="name"
        value="{{ name }}">

    <button type="button" 
        class="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600"
        data-action="live#action"
        data-live-action-param="search">Buscar</button>

    {% if this.pokemon %}
        <div class="mt-2">Resultado: {{ dump(this.pokemon) }}</div> 
    {% else %}
        <p class="text-gray-500">Pokemon no encontrado.</p>
    {% endif %}
</div>
```

3. Montar el componente en cualquier plantilla Twig:

```twig
{{ component('PokemonSearch', { name: 'Pikachu' }) }}
```

4. Comportamiento: al hacer clic en **Buscar**, `symfony/ux-live-component` hará la petición AJAX automáticamente al servidor, ejecutará el método `search()` del componente, volverá a renderizar el fragmento y actualizará el DOM.

#### B. Petición a una API externa (PokeAPI) desde un Live Component

1. Se crea un nuevo LiveComponent de la misma forma que se creó en el Punto A anterior, pero en lugar de realizar una consulta a nuestra base de datos, vamos a necesitar consultar una API Externa.

La mejor práctica recomendada es aislar la infraestructura creando un Servicio de Symfony (Service API client) e inyectarlo en tu LiveComponent. Nunca debes escribir la lógica de curl o peticiones HTTP directamente dentro del componente.Para la gestión de peticiones HTTP, el módulo estándar y recomendado es el Symfony HttpClient (ya incluido en la instalacion actual de symfony).

`src/Service/PokeAPIClient.php`

```php
<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokeAPIClient
{
    // El HttpClient se inyecta automáticamente aquí
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function getPokemonByName(string $name): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://pokeapi.co/api/v2/pokemon/$name");
            return $response->toArray(); // Convierte el JSON automáticamente a array
        } catch (\Exception $e) {
            $this->logger->error('Error fetching Pokemon from PokeAPI', ['name' => $name, 'error' => $e->getMessage()]);
            return [];
        }
    }
}

```

2. Componente que consume el Servicio `/src/Twig/Components/PokemonExternalSearch.php`:

```php
<?php

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use App\Service\PokeAPIClient;

#[AsLiveComponent()]
final class PokemonExternalSearch
{
    use DefaultActionTrait;

    public function __construct(
        private PokeAPIClient $pokeApi
    ) {
    }

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public array|null $pokemon = null;


    #[LiveAction]
    public function search(): void
    {
        $result = $this->pokeApi->getPokemonByName($this->name);
        if (is_array($result)) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
```

3. Montar el componente en una plantilla Twig como en el ejemplo anterior:

```twig
{{ component('PokemonExternalSearch', { name: '' }) }}
```

Consideraciones y consejos

- Seguridad: cuando llamas APIs externas desde el servidor, sanitiza y valida la entrada del usuario para evitar peticiones maliciosas o demasiado costosas.
- Caché: para evitar llamadas excesivas a PokeAPI en desarrollo, implementa un caché simple (por ejemplo usando `cache.app`) o limita la frecuencia de búsqueda.
- Desarrollo: usa `bin/console server:run` o `symfony serve` y revisa la consola del navegador para ver las solicitudes AJAX generadas por Live Components.

Con esto tendrás dos flujos: uno donde el componente invoca lógica interna y otro donde el servidor actúa como proxy hacia una API externa y actualiza el fragmento en la página sin recargar.


### 4.12.2 · Alternativa: Stimulus + `fetch` (llamar PokeAPI desde el navegador)

Si prefieres más control cliente-side o quieres llamar directamente a APIs públicas como PokeAPI, puedes usar un controlador Stimulus que realice una petición `fetch` y actualice el DOM.

Pasos detallados:

1) Crear el controlador Stimulus (JS o TS)

`assets/controllers/search_pokemon_controller.js`:

```
dconsole make:stimulus-controller search-pokemon
```

```js
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['pokemonName', 'pokemonDetails', 'spinner']

    initialize() {
    }

    connect() {
        // add or remove classes, attributes, dispatch custom events, etc.
        // this.fooTarget.addEventListener('click', this._fooBar)
    }

    // Add custom controller actions here
    // fooBar() { this.fooTarget.classList.toggle(this.bazClass) }

    disconnect() {
        // Here you should remove all event listeners added in "connect()" 
        // this.fooTarget.removeEventListener('click', this._fooBar)
    }

    async search() {
        const name = this.pokemonNameTarget.value.trim().toLowerCase();
        if (!name) {
            alert('Please enter the pokemon name');
            return;
        }

        this.showSpinner(true);

        try {
            const res = await fetch(`https://pokeapi.co/api/v2/pokemon/${encodeURIComponent(name)}`);
            if (!res.ok) throw new Error('Not found');
            const data = await res.json();
            this.renderPokemon(data);
        } catch (e) {
            console.error("Error: " + e.message);
            this.pokemonDetailsTarget.innerHTML = `<div class="text-red-600">Pokemon no encontrado</div>`;
        } finally {
            this.showSpinner(false);
        }
    }

    renderPokemon(data) {
        this.pokemonDetailsTarget.innerHTML = `
            <div class="flex items-center gap-4">
                <img src="${data.sprites.front_default}" alt="${data.name}" class="w-20 h-20">
                <div>
                    <h3 class="text-xl font-bold">${data.name}</h3>
                    <div>HP: ${data.stats.find(s => s.stat.name === 'hp')?.base_stat ?? '-'} </div>
                </div>
            </div>`;
    }

    showSpinner(visible) {
        // Simple toggle to show/hide the spinner (.toggle, .replace can also be used)
        if (visible) {
            this.spinnerTarget.classList.remove("hidden")
        } else {
            this.spinnerTarget.classList.add('hidden');
        }
    }
}
```

2) Registrar/usar el controlador en Twig

En la plantilla donde quieras el buscador (por ejemplo `templates/home/index.html.twig`):

```twig
<div class="mt-8 p-6 bg-amber-50 rounded-xl border border-amber-200" {{ stimulus_controller('search-pokemon') }}>
    <h2 class="text-xl font-bold text-amber-700 mb-2">Buscar Pokemon - Stimulus + Fech - External API</h2>
    <input type="text" {{ stimulus_target('search-pokemon', 'pokemonName') }} placeholder="Nombre del Pokemon" class="border rounded px-3 py-2 mr-2">

    <button type="button" {{ stimulus_action('search-pokemon', 'search') }} class="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600">
        Buscar
    </button>

    <div class="mt-3 text-lg font-medium" {{ stimulus_target('search-pokemon', 'pokemonDetails') }}></div>
    <div {{ stimulus_target('search-pokemon', 'spinner') }} class="hidden">
        {{ ux_icon('heroicons:arrow-path', {class: 'size-6 text-gray-500 hover:text-indigo-600'}) }}
    </div>
</div>
```

3) Compilar/gestionar assets

- Si usas sólo JS (archivo `assets/controllers/pokemon_controller.js`) y AssetMapper, no necesitas build adicional: AssetMapper copia el archivo a `public/assets` cuando sea necesario.
- Si usas TypeScript, compila `.ts` a `.js` (ver la sección anterior sobre `tsconfig.json` y `npx tsc --watch`).

4) Consideraciones

- CORS: PokeAPI permite peticiones GET desde el navegador; si trabajas con otra API que no permita CORS, crea un endpoint interno (proxy) que haga la llamada y devuelva el JSON.
- Seguridad: para GET no necesitas CSRF, pero para POST sí. Maneja errores y rate limits en el cliente.
- UX: añade `debounce` si quieres buscar al teclear; muestra spinner y mensajes de error claros.
- Caché: para reducir llamadas públicas, cachea en `localStorage` o implementa cache en el servidor.

5) Alternativa híbrida (Opcional)

- Si quieres control cliente-side para la latencia inicial pero validación/registro en servidor, haz que Stimulus llame a un endpoint interno (`/api/pokemon/{name}`) que valide, cachee y luego haga la petición a PokeAPI.

Ejemplo de endpoint interno (rápido):

```php
// src/Controller/Api/PokemonController.php
namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokemonController
{
    public function __construct(private HttpClientInterface $httpClient) {}

    #[Route('/api/pokemon/{name}', name: 'api_pokemon_get', methods: ['GET'])]
    public function show(string $name): JsonResponse
    {
        $url = sprintf('https://pokeapi.co/api/v2/pokemon/%s', rawurlencode(strtolower($name)));
        try {
            $resp = $this->httpClient->request('GET', $url);
            return new JsonResponse($resp->toArray());
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'not found'], Response::HTTP_NOT_FOUND);
        }
    }
}
```

Usa Stimulus para llamar `/api/pokemon/{name}` en vez de la URL pública si quieres evitar CORS o centralizar caché/limites.


## Paso 4.13 · Workflow recomendado de desarrollo

Cuando estés desarrollando, abre **dos terminales**:

**Terminal 1 (Tailwind en modo watch):**

```bash
docker compose exec php php bin/console tailwind:build --watch
```

**Terminal 2 (logs y comandos):**

```bash
# Ver logs en vivo
docker compose logs -f php

# Cuando necesites ejecutar algo:
docker compose exec php php bin/console <comando>
```

## Paso 4.14 · Comando combinado para desarrollo

Symfony incluye un command-runner que arranca Tailwind y otros watchers a la vez. Si quieres usarlo, instala:

```bash
docker compose exec php composer require --dev symfony/web-app-meta
```

Ya viene con `symfony/runtime` la posibilidad de definir tareas. Pero para Docker, lo más simple es seguir con dos terminales.

---

✅ Frontend listo: Tailwind 4 + Stimulus funcionando con hot reload.

➡️ Continúa en [`05-php-cs-fixer.md`](./05-php-cs-fixer.md).
