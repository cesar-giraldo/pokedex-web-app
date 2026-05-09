# 02 · Instalar Symfony 8 dentro del contenedor

Ahora que tenemos los contenedores corriendo, vamos a instalar Symfony **dentro** del contenedor `php`. Toda la magia ocurre con Composer.

> **Importante:** Trabajaremos siempre desde dentro del contenedor para evitar problemas de versiones de PHP en tu máquina. Los comandos con prefijo 🐳 se ejecutan así:
>
> ```bash
> docker compose exec php <comando>
> ```

---

## Paso 2.1 · Crear el esqueleto de Symfony 8

Symfony se distribuye como un paquete Composer llamado `symfony/skeleton`. Instalaremos la versión `8.0.*` (que automáticamente trae el último parche disponible: 8.0.10, 8.0.11, etc.).

> **⚠️ Atención:** Composer `create-project` falla si la carpeta destino no está vacía, y la nuestra ya tiene `Dockerfile`, `compose.yaml`, `.git/`, `.gitignore`, etc. Por eso usamos el truco de instalar en `/tmp` y luego mover los archivos sin sobrescribir.

🐳 Ejecuta estos tres comandos en orden:

```bash
# 1. Crear Symfony en una carpeta temporal (vacía) dentro del contenedor
docker compose exec php composer create-project symfony/skeleton:"8.0.*" /tmp/symfony-new --no-interaction

# 2. Copiar todo (incluyendo archivos ocultos) a /app SIN sobrescribir lo existente
docker compose exec php bash -c 'shopt -s dotglob && cp -rn /tmp/symfony-new/. /app/'

# 3. Borrar la carpeta temporal
docker compose exec php rm -rf /tmp/symfony-new
```

¿Qué hace cada flag del comando 2?

| Flag                   | Significado                                                          |
| ---------------------- | -------------------------------------------------------------------- |
| `shopt -s dotglob`     | Hace que `*` también incluya archivos ocultos como `.env`.           |
| `cp -r`                | Copia recursiva (carpetas y subcarpetas).                            |
| `cp -n` (`no-clobber`) | **No sobrescribe** archivos que ya existan en el destino.            |
| `/tmp/symfony-new/.`   | El punto final hace que copie el **contenido**, no la carpeta misma. |

Cuando termine, tu carpeta tendrá esta estructura nueva (los archivos que tú ya creaste se conservan):

```text
mi-app-symfony/
├── .git/                  ← (tuyo)
├── .gitignore             ← (tuyo)
├── Dockerfile             ← (tuyo)
├── compose.yaml           ← (tuyo)
├── docker/                ← (tuyo)
├── bin/
│   └── console            ← CLI de Symfony
├── config/                ← Configuración (YAML)
├── public/
│   └── index.php          ← Punto de entrada web
├── src/                   ← Tu código PHP
├── var/                   ← Caché y logs (auto-generado)
├── vendor/                ← Dependencias de Composer
├── .env                   ← Variables de entorno
├── composer.json
└── composer.lock
```

> **Tip:** Si ves el error `Permission denied`, asegúrate de que tu usuario sea dueño de la carpeta:
>
> ```bash
> sudo chown -R $USER:$USER .
> ```

## Paso 2.2 · Instalar el "webapp pack"

`symfony/skeleton` es minimalista (solo backend). Para una app web completa, instala el meta-paquete `symfony/webapp-pack`, que incluye Twig, Doctrine, AssetMapper, formularios, Stimulus, etc.

🐳 Dentro del contenedor:

```bash
docker compose exec php composer require webapp
```

> **Nota:** `webapp` es un alias del paquete `symfony/webapp-pack`. Symfony Flex lo reconoce y lo expande.

### ⚠️ Importante: cuando Flex pregunte sobre "Docker configuration"

Durante la instalación, **Symfony Flex te preguntará** algo como:

```text
For additional features, sometimes Docker configuration is recommended.
Do you want to include Docker configuration from recipes?
[y] Yes  [n] No  [p] Yes permanently  [x] No permanently  [?] Help
(defaults to n):
```

**Responde `x`** ("No permanently"). 👈

¿Por qué? Porque ya configuraste PostgreSQL 18 a mano en `compose.yaml`. Si dejas que Flex añada su propio bloque Docker:

- Te metería un **segundo servicio `database`** o sobrescribiría el tuyo (posiblemente con MariaDB o un Postgres genérico).
- Te cambiaría el mount del volumen y reaparecería el error de Postgres 18 que ya solucionaste.
- Podría romper el `healthcheck` y el `depends_on`.

Responder `x` le dice a Flex: "para **este proyecto**, **nunca** vuelvas a preguntar esto". Cada paquete que instales después (`stimulus-bundle`, `mailer`, etc.) también haría la misma pregunta, así te la ahorras de una vez.

> Internamente, Flex añade esto a tu `composer.json`:
>
> ```json
> {
>     "extra": {
>         "symfony": {
>             "docker": false
>         }
>     }
> }
> ```
>
> Es seguro y reversible: borra esa línea cuando quieras volver a recibir las preguntas.

### El resto de las recipes sí queremos aplicarlas

Para cualquier otra pregunta sobre **recipes que NO sean Docker** (las que crean archivos como `templates/base.html.twig`, `assets/app.js`, `config/packages/*.yaml`), responde `y`. Esas son las que configuran Symfony por ti y son las que sí necesitamos.

Después de esto tendrás una nueva estructura:

```text
mi-app-symfony/
├── assets/                ← JS y CSS de tu app
│   ├── app.js
│   ├── styles/
│   │   └── app.css
│   ├── controllers/       ← Controladores Stimulus
│   └── controllers.json
├── templates/             ← Plantillas Twig
│   └── base.html.twig
├── importmap.php          ← Mapa de imports (AssetMapper)
└── ... (lo anterior)
```

## Paso 2.3 · Verificar que Symfony funciona

Abre tu navegador y ve a:

🌐 <https://localhost>

> La primera vez tu navegador mostrará una advertencia de certificado: es porque FrankenPHP genera un certificado autofirmado para `localhost`. Acepta la excepción ("Avanzado" → "Continuar").

Deberías ver la página de bienvenida de Symfony con el mensaje:

> **Welcome to Symfony 8.0.x**

Si la ves, ¡felicidades! 🎉 Symfony está corriendo dentro de Docker.

## Paso 2.4 · Crear tu primer controlador

Vamos a crear una página de inicio personalizada. Symfony incluye el **MakerBundle**, que genera código por nosotros.

🐳 Genera un controlador llamado `HomeController`:

```bash
docker compose exec php php bin/console make:controller HomeController
```

Esto crea dos archivos:

- `src/Controller/HomeController.php`
- `templates/home/index.html.twig`

Edita `src/Controller/HomeController.php` para que la ruta sea la raíz `/`:

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'mensaje' => '¡Hola desde Symfony 8 + Docker!',
        ]);
    }
}
```

Edita la plantilla `templates/home/index.html.twig`:

```twig
{% extends 'base.html.twig' %}

{% block title %}Inicio · Mi App{% endblock %}

{% block body %}
    <h1>{{ mensaje }}</h1>
    <p>Si ves este mensaje, todo está funcionando.</p>
{% endblock %}
```

Recarga <https://localhost> y verás tu nueva página.

## Paso 2.5 · Comandos útiles del MakerBundle

El MakerBundle te ahorra mucho tiempo. Aquí los comandos más usados:

| Comando                                    | Genera                                  |
| ------------------------------------------ | --------------------------------------- |
| `make:controller <Nombre>`                 | Un controlador + plantilla Twig         |
| `make:entity <Nombre>`                     | Una entidad Doctrine (modelo de datos)  |
| `make:form <Nombre>`                       | Una clase de formulario                 |
| `make:migration`                           | Una migración Doctrine                  |
| `make:user`                                | Una entidad User para autenticación     |
| `make:auth`                                | Sistema de login completo               |
| `make:test`                                | Una clase de test                       |
| `make:command <Nombre>`                    | Un comando de consola personalizado     |

Para ver todos los comandos disponibles:

```bash
docker compose exec php php bin/console list make
```

## Paso 2.6 · La consola `bin/console`

`bin/console` es la herramienta de línea de comandos de Symfony. Algunos comandos esenciales:

```bash
# Listar todas las rutas registradas
docker compose exec php php bin/console debug:router

# Ver toda la configuración de un bundle
docker compose exec php php bin/console debug:config framework

# Limpiar la caché (útil si algo se comporta raro)
docker compose exec php php bin/console cache:clear

# Ver los servicios registrados en el contenedor
docker compose exec php php bin/console debug:container
```

> **Tip de productividad:** Crea un alias en tu `.bashrc` o `.zshrc`:
>
> ```bash
> alias dphp='docker compose exec php'
> alias dconsole='docker compose exec php php bin/console'
> ```
>
> Así podrás escribir simplemente `dconsole make:controller MiController`.

## Paso 2.7 · (Opcional) Activar el modo worker de FrankenPHP

Ahora que `public/index.php` ya existe (lo creó Symfony), podemos activar el modo **worker** de FrankenPHP para tener un rendimiento mucho mejor.

> **¿Qué hace el modo worker?** En lugar de inicializar el kernel de Symfony en cada petición HTTP (lento), lo carga **una sola vez** al arrancar el contenedor y lo deja en memoria. Cada petición reusa el mismo proceso. El resultado: ~5–10× más rápido en peticiones simples.

> **🎉 En Symfony 8 no hay que instalar ningún paquete extra.** Desde `symfony/runtime` v8.0.0 (que ya viene con `webapp-pack`), Symfony incluye la clase `FrankenPhpWorkerRunner` y **detecta automáticamente** si FrankenPHP está en modo worker. Antes había que instalar `runtime/frankenphp-symfony` y poner `APP_RUNTIME` en `.env` — eso ya **no es necesario** y de hecho ese paquete ni siquiera es compatible con Symfony 8.

### Opción A · Activar el worker con un Caddyfile propio (recomendado)

Esta es la forma más confiable: montamos un Caddyfile a nuestra medida. Crea el archivo `docker/Caddyfile` con este contenido:

```caddy
{
	# Modo worker: el kernel de Symfony queda en memoria entre peticiones
	frankenphp {
		worker ./public/index.php
	}
}

{$SERVER_NAME:localhost} {
	log {
		output stderr
		format console
	}

	root * /app/public
	encode zstd br gzip

	php_server {
		try_files {path} index.php
	}
}
```

Añade el volumen al servicio `php` en `compose.yaml`:

```diff
   php:
     ...
     volumes:
       - ./:/app:cached
       - caddy_data:/data
       - caddy_config:/config
+      - ./docker/Caddyfile:/etc/frankenphp/Caddyfile:ro
```

Reinicia (no requiere rebuild):

```bash
docker compose up -d --force-recreate php
```

### Opción B · Activar el worker solo con la variable de entorno

Más rápido, pero a veces la inyección falla en algunas versiones de la imagen. Edita `compose.yaml` y descomenta:

```diff
     environment:
-      # FRANKENPHP_CONFIG: "worker ./public/index.php"
+      FRANKENPHP_CONFIG: "worker ./public/index.php"
```

Y reinicia: `docker compose up -d --force-recreate php`.

### Cómo confirmar que el worker está activo

Los logs de FrankenPHP cambian entre versiones, así que la verificación más fiable es funcional. Crea temporalmente esta ruta en `src/Controller/HomeController.php`:

```php
#[Route('/_worker-check', name: 'worker_check')]
public function workerCheck(): \Symfony\Component\HttpFoundation\JsonResponse
{
    return $this->json([
        'frankenphp_worker' => isset($_SERVER['FRANKENPHP_WORKER']) ? 'YES' : 'NO',
    ]);
}
```

Visita <https://localhost/_worker-check>:

- `"frankenphp_worker": "YES"` → el worker está corriendo. ✅
- `"frankenphp_worker": "NO"` → no se activó; revisa los pasos.

Borra esa ruta cuando termines la prueba.

Diagnóstico rápido si no arranca:

```bash
# 1. ¿Llegó la variable al contenedor?
docker compose exec php env | grep -i franken

# 2. ¿Cómo quedó el Caddyfile real?
docker compose exec php cat /etc/frankenphp/Caddyfile

# 3. ¿Qué dice el log completo?
docker compose logs php | grep -iE "worker|frankenphp"
```

### ⚠️ Compromiso: hot reload vs velocidad

En modo worker, el kernel de Symfony queda **cargado en memoria**, así que los cambios en archivos PHP **no se reflejan** en la siguiente petición. Tienes tres opciones:

| Tu prioridad                                  | Recomendación                                                       |
| --------------------------------------------- | ------------------------------------------------------------------- |
| **Hot reload cómodo durante el desarrollo**   | Deja **comentada** la línea `FRANKENPHP_CONFIG` (sin worker).       |
| **Velocidad máxima, recargas manuales**       | Activa worker y haz `docker compose restart php` cuando edites PHP. |
| **Velocidad + auto-reload**                   | Configura un `Caddyfile` personalizado con la directiva `watch`.    |

Para la mayoría de los devs, **dejar el worker desactivado en desarrollo es lo más práctico**. Sin worker, FrankenPHP ya es más rápido que Apache + PHP-FPM. El worker brilla de verdad en **staging/producción**, donde el código no cambia entre peticiones.

> **Nota:** No instales `runtime/frankenphp-symfony`. Ese paquete fue el precursor de la integración nativa y solo es compatible con Symfony 6/7. En Symfony 8, Composer lo rechazará con un error de resolución de dependencias.

---

✅ Ya tienes Symfony funcionando. Ahora vamos a conectarlo con PostgreSQL.

➡️ Continúa en [`03-postgresql-y-doctrine.md`](./03-postgresql-y-doctrine.md).
