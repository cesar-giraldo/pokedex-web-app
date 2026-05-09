# 06 · Comandos del día a día y solución de problemas

Esta es tu hoja de referencia. Vuelve aquí cada vez que olvides un comando.

---

## 6.1 · Ciclo de vida de los contenedores

| Acción                            | Comando                                                |
| --------------------------------- | ------------------------------------------------------ |
| Construir imágenes                | `docker compose build`                                 |
| Construir desde cero (sin caché)  | `docker compose build --pull --no-cache`               |
| Levantar todo                     | `docker compose up -d`                                 |
| Detener (preserva datos)          | `docker compose stop`                                  |
| Detener y eliminar contenedores   | `docker compose down`                                  |
| Detener y borrar volúmenes (⚠️ borra la BD) | `docker compose down -v`                     |
| Reiniciar un servicio             | `docker compose restart php`                           |
| Ver estado de los servicios       | `docker compose ps`                                    |
| Ver logs en vivo                  | `docker compose logs -f`                               |
| Logs de un servicio específico    | `docker compose logs -f php`                           |

## 6.2 · Trabajar dentro del contenedor

| Acción                            | Comando                                                |
| --------------------------------- | ------------------------------------------------------ |
| Abrir una shell interactiva       | `docker compose exec php bash`                         |
| Ejecutar un comando único         | `docker compose exec php <comando>`                    |
| Ejecutar como otro usuario        | `docker compose exec -u root php bash`                 |

> **Tip pro:** Crea estos alias en tu `.zshrc`/`.bashrc`:
>
> ```bash
> alias dc='docker compose'
> alias dphp='docker compose exec php'
> alias dconsole='docker compose exec php php bin/console'
> alias dcomposer='docker compose exec php composer'
> ```

## 6.3 · Composer y dependencias

```bash
# Instalar dependencias declaradas en composer.json
docker compose exec php composer install

# Añadir una nueva librería
docker compose exec php composer require <vendor/paquete>

# Añadir una librería solo de desarrollo
docker compose exec php composer require --dev <vendor/paquete>

# Actualizar todas las dependencias
docker compose exec php composer update

# Eliminar una dependencia
docker compose exec php composer remove <vendor/paquete>

# Ver lista de paquetes instalados
docker compose exec php composer show
```

## 6.4 · Symfony console

```bash
# Listar todos los comandos disponibles
docker compose exec php php bin/console

# Limpiar caché
docker compose exec php php bin/console cache:clear

# Ver todas las rutas
docker compose exec php php bin/console debug:router

# Detalle de una ruta específica
docker compose exec php php bin/console debug:router app_home

# Ver servicios del contenedor
docker compose exec php php bin/console debug:container

# Ver configuración de un bundle
docker compose exec php php bin/console debug:config framework

# Ver variables de entorno cargadas
docker compose exec php php bin/console debug:dotenv
```

## 6.5 · Doctrine

```bash
# Crear / borrar la base de datos
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:database:drop --force

# Generar una migración a partir de los cambios en entidades
docker compose exec php php bin/console make:migration

# Ejecutar migraciones pendientes
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Estado de migraciones
docker compose exec php php bin/console doctrine:migrations:status

# Validar mapeo de entidades
docker compose exec php php bin/console doctrine:schema:validate

# Ejecutar SQL crudo
docker compose exec php php bin/console dbal:run-sql "SELECT 1"

# Cargar fixtures (datos de prueba)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

## 6.6 · Frontend (Tailwind + AssetMapper)

```bash
# Compilar Tailwind una vez
docker compose exec php php bin/console tailwind:build

# Compilar Tailwind y observar cambios (dev)
docker compose exec php php bin/console tailwind:build --watch

# Compilar Tailwind minificado (producción)
docker compose exec php php bin/console tailwind:build --minify

# Ver assets registrados
docker compose exec php php bin/console debug:asset-map

# Compilar y copiar todos los assets a public/assets/ (producción)
docker compose exec php php bin/console asset-map:compile
```

## 6.7 · Calidad de código

```bash
# Verificar estilo (sin modificar)
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff

# Aplicar correcciones
docker compose exec php vendor/bin/php-cs-fixer fix

# Análisis estático con PHPStan
docker compose exec php vendor/bin/phpstan analyse
```

## 6.8 · Tests (PHPUnit)

```bash
# Crear un test
docker compose exec php php bin/console make:test

# Ejecutar todos los tests
docker compose exec php php bin/phpunit

# Ejecutar un test específico
docker compose exec php php bin/phpunit tests/Controller/HomeControllerTest.php

# Ejecutar con cobertura
docker compose exec php php bin/phpunit --coverage-html var/coverage
```

## 6.9 · Acceso directo a PostgreSQL

```bash
# Cliente psql interactivo
docker compose exec database psql -U app -d app

# Ejecutar SQL desde la terminal
docker compose exec database psql -U app -d app -c "SELECT * FROM producto"

# Hacer un dump
docker compose exec database pg_dump -U app app > dump.sql

# Restaurar un dump
cat dump.sql | docker compose exec -T database psql -U app -d app
```

---

## 6.10 · Solución de problemas comunes

### ❌ "permission denied" al crear archivos

**Causa:** El contenedor crea archivos como `root` que tu usuario no puede modificar.

**Solución (Linux/macOS):**

```bash
sudo chown -R $USER:$USER .
```

**Prevención:** Edita el `Dockerfile` y añade un usuario con tu UID:

```dockerfile
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN groupadd -g ${GROUP_ID} app && \
    useradd -u ${USER_ID} -g app -m app

USER app
```

Y construye con:

```bash
docker compose build --build-arg USER_ID=$(id -u) --build-arg GROUP_ID=$(id -g)
```

### ❌ "Connection refused" al hacer migraciones

**Causa:** El contenedor PHP arrancó antes que PostgreSQL.

**Solución:** Reinicia y espera al healthcheck:

```bash
docker compose down
docker compose up -d
docker compose ps  # espera a que database diga "healthy"
```

### ❌ Tailwind no aplica estilos al cambiar plantillas

**Causa:** No estás corriendo `tailwind:build --watch`.

**Solución:** Abre una terminal dedicada con:

```bash
docker compose exec php php bin/console tailwind:build --watch
```

### ❌ "Cannot redeclare class" o errores raros tras instalar paquetes

**Causa:** Caché obsoleta.

**Solución:**

```bash
docker compose exec php php bin/console cache:clear
docker compose exec php composer dump-autoload
```

### ❌ "SSL certificate problem" en navegador

**Causa:** Es normal, FrankenPHP usa un certificado autofirmado en `localhost`.

**Solución:** Acepta la excepción manualmente, o si quieres certificados válidos en local:

```bash
# Instala mkcert (mkcert.dev) y luego:
mkcert -install
```

### ❌ "Port is already allocated"

**Causa:** Otro proceso ya está usando el puerto 80, 443 o 5432.

**Solución 1:** Libera el puerto:

```bash
# Ver qué proceso usa el puerto 80
sudo lsof -i :80
```

**Solución 2:** Cambia el puerto en `compose.yaml`:

```yaml
ports:
  - "8080:80"   # accede ahora en http://localhost:8080
  - "8443:443"
  - "5433:5432"
```

### ❌ Cambios en `Dockerfile` no se reflejan

**Causa:** Docker reusa la imagen cacheada.

**Solución:**

```bash
docker compose build --pull --no-cache
docker compose up -d --force-recreate
```

### ❌ Las migraciones de Doctrine fallan con "Schema sync"

**Causa:** Tu `serverVersion` en `DATABASE_URL` no coincide con el real.

**Solución:** Confirma con:

```bash
docker compose exec database psql -U app -c "SHOW server_version"
```

Y ajusta `serverVersion=18` en el `compose.yaml` si es necesario.

### ❌ Composer dice "Your requirements could not be resolved"

**Causa:** Conflicto de versiones PHP.

**Solución:** Confirma que el contenedor usa PHP 8.4:

```bash
docker compose exec php php -v
```

Si no, revisa que el `Dockerfile` use `dunglas/frankenphp:1-php8.4`.

### ❌ El contenedor PHP se reinicia constantemente

**Causa:** Error fatal en `config/` o sintaxis PHP rota.

**Solución:**

```bash
docker compose logs --tail=50 php
```

Lee los logs y arregla el archivo señalado.

### ❌ `Command "doctrine:query:sql" is not defined`

**Causa:** En `doctrine/doctrine-bundle` 2.13+ (lo que viene con Symfony 8) el comando `doctrine:query:sql` fue renombrado a `dbal:run-sql`.

**Solución:** Usa el nombre nuevo:

```bash
docker compose exec php php bin/console dbal:run-sql "SELECT current_database()"
```

Para ver todos los comandos disponibles relacionados:

```bash
docker compose exec php php bin/console list dbal
docker compose exec php php bin/console list doctrine
```

> **Otros comandos útiles del namespace `dbal`:** `dbal:run-sql`, `dbal:server-version`, `dbal:reserved-words`. El `doctrine:query:dql` (para queries del ORM con DQL) sí sigue existiendo, no fue renombrado.

### ❌ Edité `.env.local` pero Symfony sigue usando el valor viejo

**Causa:** En Symfony, **las variables del entorno real siempre tienen prioridad** sobre los archivos `.env.*`. Si la variable está definida en el bloque `environment:` de `compose.yaml`, en un `export` de tu shell, o en un `Dockerfile ENV`, ese valor gana sobre el de `.env.local`.

**Diagnóstico:**

```bash
docker compose exec php php bin/console debug:dotenv
```

Si en la columna `File` ves `(real env)` para tu variable, ese es el problema.

**Solución:**

1. Quita la variable del bloque `environment:` de `compose.yaml`.
2. Asegúrate de que esté en `.env` (con valor por defecto) y `.env.local` (con tu valor personal, gitignored).
3. Recrea el contenedor para que las variables se actualicen:

```bash
docker compose up -d --force-recreate php
docker compose exec php php bin/console cache:clear
```

> **Regla práctica:** En `compose.yaml` deja solo variables que sean propias de Docker (`SERVER_NAME`, `APP_ENV`, `APP_SECRET`). Cualquier valor que un dev quiera personalizar debe vivir en `.env` / `.env.local`.

### ❌ Activé el modo worker pero los logs no muestran `workers initialized`

**Causa:** Las versiones recientes de FrankenPHP (1.9+) cambiaron los mensajes de log al iniciar; "workers initialized" puede no aparecer aunque el worker sí esté funcionando. También es posible que la variable `FRANKENPHP_CONFIG` no se haya inyectado correctamente al `Caddyfile`.

**Diagnóstico (test funcional):** Crea temporalmente esta ruta en cualquier controlador:

```php
#[Route('/_worker-check')]
public function workerCheck(): \Symfony\Component\HttpFoundation\JsonResponse
{
    return $this->json([
        'frankenphp_worker' => isset($_SERVER['FRANKENPHP_WORKER']) ? 'YES' : 'NO',
    ]);
}
```

Visita `https://localhost/_worker-check`. Si responde `YES` el worker está activo.

**Solución si responde `NO`:** monta un `Caddyfile` propio (más confiable que la variable de entorno). Ver Paso 2.7, "Opción A" en `02-instalar-symfony.md`.

### ❌ Composer rechaza `runtime/frankenphp-symfony` en Symfony 8

**Mensaje:**

```text
runtime/frankenphp-symfony[*] require symfony/dependency-injection ^5.4 || ^6.0 || ^7.0
- but the package is fixed to v8.0.x (lock file version)
```

**Causa:** El paquete `runtime/frankenphp-symfony` solo soporta Symfony hasta la versión 7. En Symfony 8 ya **no es necesario**: la clase `FrankenPhpWorkerRunner` está integrada directamente en `symfony/runtime` v8.0.0+ (que ya viene con `webapp-pack`) y se activa automáticamente cuando detecta el modo worker.

**Solución:** No instales el paquete. Solo activa el modo worker en `compose.yaml`:

```yaml
environment:
  FRANKENPHP_CONFIG: "worker ./public/index.php"
```

Y reinicia: `docker compose up -d --force-recreate php`. Symfony hará el resto.

### ❌ Tras instalar un paquete, mi `compose.yaml` aparece modificado / hay un servicio `database` duplicado

**Causa:** Respondiste `y` a la pregunta de Flex sobre "Docker configuration". El recipe del paquete (típicamente `doctrine/doctrine-bundle` o `symfony/mailer`) añadió su propio bloque Docker encima del tuyo.

**Solución:**

```bash
# Revierte los cambios al compose.yaml y Dockerfile
git checkout -- compose.yaml Dockerfile

# Configura Flex para que NUNCA vuelva a preguntar en este proyecto
docker compose exec php composer config extra.symfony.docker false
```

A partir de ahí, ningún `composer require` futuro tocará tus archivos Docker.

### ❌ Composer: `Project directory "/app/." is not empty`

**Causa:** Composer `create-project` se rehúsa a instalar Symfony en una carpeta que ya tiene archivos (los del setup de Docker que creaste antes).

**Solución:** Instalar en una carpeta temporal y mover el contenido sin sobrescribir lo tuyo:

```bash
docker compose exec php composer create-project symfony/skeleton:"8.0.*" /tmp/symfony-new --no-interaction
docker compose exec php bash -c 'shopt -s dotglob && cp -rn /tmp/symfony-new/. /app/'
docker compose exec php rm -rf /tmp/symfony-new
```

`cp -n` no sobrescribe archivos existentes, así que `Dockerfile`, `compose.yaml`, `.gitignore` y `.git/` quedan intactos.

### ❌ FrankenPHP: `worker filename is invalid "./public/index.php": lstat public: no such file or directory`

**Causa:** El modo worker de FrankenPHP está activo pero `public/index.php` aún no existe (porque Symfony todavía no se instaló).

**Solución 1 (si aún no instalas Symfony):** Comenta o borra la línea `FRANKENPHP_CONFIG: "worker ./public/index.php"` en `compose.yaml` y también en `Dockerfile` si aparece. Luego:

```bash
docker compose down
docker compose build --no-cache php
docker compose up -d
```

Procede con la instalación de Symfony (paso 02). Una vez que `public/index.php` exista, puedes reactivar el modo worker.

**Solución 2 (si Symfony sí está instalado pero el archivo no aparece):** Confirma que el volumen `./:/app` está bien montado:

```bash
docker compose exec php ls -la /app/public/index.php
```

Si el archivo no aparece, revisa que el `WORKDIR /app` del Dockerfile coincida con el destino del volumen en `compose.yaml`.

---

## 6.11 · Estructura final del proyecto

Después de seguir toda la guía, tu carpeta debería verse así:

```text
mi-app-symfony/
├── .github/
│   └── workflows/
│       └── cs.yml
├── assets/
│   ├── app.js
│   ├── bootstrap.js
│   ├── controllers/
│   │   └── hello_controller.js
│   └── styles/
│       └── app.css
├── bin/
│   ├── console
│   └── phpunit
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── symfonycasts_tailwind.yaml
│   │   └── ...
│   ├── routes.yaml
│   └── services.yaml
├── docker/
│   └── php/
│       └── php.ini
├── migrations/
├── public/
│   ├── index.php
│   └── assets/        (generado, no commiteado)
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   └── Kernel.php
├── templates/
│   ├── base.html.twig
│   └── home/
│       └── index.html.twig
├── tests/
├── var/                (generado, no commiteado)
├── vendor/             (generado, no commiteado)
├── .env
├── .gitignore
├── .php-cs-fixer.dist.php
├── compose.yaml
├── composer.json
├── composer.lock
├── Dockerfile
├── importmap.php
└── README.md
```

## 6.12 · Próximos pasos sugeridos

1. **Autenticación:** `php bin/console make:user` y `make:auth`.
2. **Formularios:** `php bin/console make:form ProductoType`.
3. **API REST:** instala `api-platform/core` para una API en minutos.
4. **Mailer:** Symfony Mailer + MailHog en otro contenedor para probar emails.
5. **Producción:** revisa <https://github.com/dunglas/symfony-docker> para Dockerfile multi-stage optimizado para producción.

---

## 6.13 · Referencias oficiales

- 📘 Documentación de Symfony 8: <https://symfony.com/doc/current/index.html>
- 🐳 Symfony Docker: <https://github.com/dunglas/symfony-docker>
- 🎨 Tailwind CSS 4: <https://tailwindcss.com/docs>
- ⚡ Stimulus: <https://stimulus.hotwired.dev/>
- 🧩 Symfony UX: <https://ux.symfony.com/>
- 🐘 PostgreSQL 18: <https://www.postgresql.org/docs/18/>
- 🧹 PHP CS Fixer: <https://cs.symfony.com/>
- 🦅 FrankenPHP: <https://frankenphp.dev/docs/>

---

🎉 **¡Felicidades!** Has construido un stack moderno PHP completo, dockerizado, listo para crecer. Cualquier desarrollador puede clonar tu repo y arrancar todo con dos comandos:

```bash
git clone <tu-repo>
cd <tu-repo>
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Y ya está funcionando. Esa es la magia de Docker. 🚀
