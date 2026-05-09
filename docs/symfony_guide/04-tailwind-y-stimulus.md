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
