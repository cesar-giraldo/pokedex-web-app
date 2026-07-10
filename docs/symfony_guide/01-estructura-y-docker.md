# 01 · Estructura del proyecto y Docker

En esta sección crearemos la carpeta del proyecto y prepararemos los archivos Docker que correrán **PHP 8.4 + FrankenPHP** y **PostgreSQL 18**.

> **¿Por qué FrankenPHP?** Es un servidor moderno escrito en Go (sobre Caddy) que combina servidor web + intérprete PHP en un solo proceso. Trae HTTPS automático, HTTP/2, HTTP/3 y modo "worker" (PHP siempre en memoria, mucho más rápido). Es el setup recomendado por Dunglas (creador de API Platform y mantenedor de Symfony).

---

## Paso 1.1 · Crear la carpeta del proyecto

💻 En tu máquina (host):

```bash
mkdir mi-app-symfony
cd mi-app-symfony
git init
```

> El `git init` no es obligatorio para que funcione, pero es altamente recomendable empezar a versionar desde el día 1.

## Paso 1.2 · Crear un archivo `.gitignore`

Crea el archivo `.gitignore` con este contenido:

```gitignore
###> symfony/framework-bundle ###
/.env.local
/.env.local.php
/.env.*.local
/config/secrets/prod/prod.decrypt.private.php
/public/bundles/
/var/
/vendor/
###< symfony/framework-bundle ###

###> symfony/asset-mapper ###
/public/assets/
/assets/vendor/
###< symfony/asset-mapper ###

###> symfonycasts/tailwind-bundle ###
/var/tailwind/
/public/assets/styles/
###< symfonycasts/tailwind-bundle ###

###> friendsofphp/php-cs-fixer ###
/.php-cs-fixer.php
/.php-cs-fixer.cache
###< friendsofphp/php-cs-fixer ###

# Sistema operativo / editor
.DS_Store
.idea/
.vscode/
*.log
```

> Symfony irá añadiendo automáticamente bloques con la marca `###>` cuando instalemos paquetes. No los borres.

## Paso 1.3 · Crear el archivo `Dockerfile`

Crea un archivo llamado `Dockerfile` (sin extensión) en la raíz del proyecto:

```dockerfile
# syntax=docker/dockerfile:1

# ============================================================
# Imagen base: FrankenPHP con PHP 8.4
# Trae Caddy + PHP-FPM + extensiones comunes preinstaladas
# ============================================================
FROM dunglas/frankenphp:1-php8.4 AS base

# Argumentos para la zona horaria (puedes cambiarla)
ARG TZ=America/Bogota
ENV TZ=${TZ}

# 1. Dependencias del sistema necesarias para Symfony y PostgreSQL
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Extensiones PHP requeridas por Symfony + PostgreSQL
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    opcache \
    gd \
    apcu

# 3. Composer (gestor de paquetes de PHP)
COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# 4. Node.js (lo necesitaremos para algunos assets, opcional)
RUN install-php-extensions @composer && \
    apt-get update && apt-get install -y --no-install-recommends curl ca-certificates && \
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    rm -rf /var/lib/apt/lists/*

# 5. Configurar PHP para desarrollo
COPY --link docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# 6. Definir el directorio de trabajo dentro del contenedor
WORKDIR /app

# 7. FrankenPHP servirá los archivos desde /app/public por defecto
ENV SERVER_NAME=":80"

# Nota: el modo "worker" (mucho más rápido) se habilita más tarde,
# después de instalar Symfony, en el archivo 02-instalar-symfony.md.
# No lo activamos aquí porque requiere que public/index.php ya exista.

# 8. Exponer puertos HTTP/HTTPS
EXPOSE 80
EXPOSE 443
EXPOSE 443/udp
```

### ¿Qué hace cada parte?

| Sección | Propósito |
| ------- | --------- |
| `FROM dunglas/frankenphp:1-php8.4` | Partimos de una imagen oficial con PHP 8.4 + FrankenPHP. |
| `apt-get install ...` | Instala librerías del SO necesarias para extensiones PHP. |
| `install-php-extensions` | Script que viene en la imagen para instalar extensiones de PHP fácilmente. `pdo_pgsql` es **obligatorio** para PostgreSQL. |
| `COPY --from=composer/composer` | Copia el binario de Composer desde otra imagen oficial. |
| `nodejs` | Aunque AssetMapper no necesita Node, algunos paquetes (como ESLint) sí. |
| `WORKDIR /app` | Todo el código vivirá en `/app` dentro del contenedor. |
| `SERVER_NAME=":80"` | Hace que FrankenPHP escuche en el puerto 80. |

## Paso 1.4 · Crear la configuración de PHP

Crea la carpeta y el archivo `docker/php/php.ini`:

```bash
mkdir -p docker/php
```

Archivo `docker/php/php.ini`:

```ini
; Configuración PHP para desarrollo
date.timezone = America/Bogota
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120

; Mostrar errores en desarrollo (NO usar en producción)
display_errors = On
display_startup_errors = On
error_reporting = E_ALL

; OPcache (acelera PHP)
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0
```

## Paso 1.5 · Crear el archivo `compose.yaml`

Este archivo define los servicios (contenedores) que vamos a usar y cómo se comunican entre sí. Crea `compose.yaml` en la raíz:

```yaml
services:
  # ========================================================
  # Aplicación PHP + servidor web (FrankenPHP)
  # ========================================================
  php:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: app_php
    restart: unless-stopped
    ports:
      - "80:80"      # HTTP
      - "443:443"    # HTTPS
      - "443:443/udp" # HTTP/3
    volumes:
      - ./:/app:cached            # Código en vivo (hot reload)
      - caddy_data:/data          # Certificados TLS
      - caddy_config:/config      # Configuración de Caddy
    environment:
      SERVER_NAME: "https://localhost"
      APP_ENV: dev
      APP_SECRET: "ChangeMe!ThisIsADevSecret"
      # ⚠️ NO definas DATABASE_URL aquí. Si lo haces, sobrescribirá lo que
      # configures en .env / .env.local (las variables del entorno real
      # tienen prioridad sobre los archivos dotenv en Symfony).
      # Pon DATABASE_URL en el archivo .env del proyecto (ver paso 02-03).
      #
      # El modo worker de FrankenPHP se habilita en el paso 02 (después
      # de instalar Symfony) descomentando la siguiente línea:
      # FRANKENPHP_CONFIG: "worker ./public/index.php"
    depends_on:
      database:
        condition: service_healthy
    tty: true

  # ========================================================
  # Base de datos PostgreSQL 18
  # ========================================================
  database:
    image: postgres:18.3-alpine
    container_name: app_db
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    ports:
      - "${POSTGRES_PORT:-5432}:5432"   # Para conectarte desde tu cliente SQL
    volumes:
      # ⚠️ IMPORTANTE: en Postgres 18+ el volumen se monta en /var/lib/postgresql
      # (NO en /var/lib/postgresql/data como en versiones anteriores).
      # La imagen oficial guarda los datos en una subcarpeta versionada
      # (p. ej. /var/lib/postgresql/18/docker) para soportar pg_upgrade --link.
      - db_data:/var/lib/postgresql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10

# ========================================================
# Volúmenes persistentes
# ========================================================
volumes:
  db_data:
  caddy_data:
  caddy_config:
```

### Puntos clave del `compose.yaml`

- **`./:/app:cached`** — Mapea tu carpeta del proyecto al contenedor. Cualquier cambio que hagas en tu editor se refleja al instante (hot reload).
- **`${POSTGRES_*}`** — Credenciales y nombre de BD leídos del `.env` en la raíz. Un solo archivo para Docker y Symfony.
- **`DATABASE_URL`** — Se construye en `.env` a partir de `POSTGRES_*`. El host por defecto es `database` (nombre del servicio).
- **`depends_on.condition: service_healthy`** — PHP no arranca hasta que PostgreSQL esté listo para aceptar conexiones.
- **`postgres:18.3-alpine`** — Imagen oficial, variante Alpine (más liviana, ~80 MB).
- **Volumen `db_data`** — Tus datos de PostgreSQL persisten entre reinicios.

## Paso 1.6 · Construir y levantar los contenedores

💻 Por primera vez, construye las imágenes:

```bash
docker compose build --pull
```

> `--pull` fuerza a descargar la versión más reciente de las imágenes base.

Luego, levanta los contenedores:

```bash
docker compose up -d
```

> `-d` significa "detached" (en segundo plano). Sin él, verías los logs en tu terminal.

Verifica que ambos servicios estén corriendo:

```bash
docker compose ps
```

Deberías ver algo como:

```text
NAME      STATUS                   PORTS
app_db    Up (healthy)             0.0.0.0:5432->5432/tcp
app_php   Up                       0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp
```

## Paso 1.7 · Verificar que PHP funciona

Entra al contenedor y comprueba la versión de PHP:

```bash
docker compose exec php php -v
```

Salida esperada:

```text
PHP 8.4.x (cli) ...
```

> **Nota importante:** Si en este momento abres <https://localhost> verás un error 404 o una página vacía. **Eso es totalmente normal.** Como aún no has instalado Symfony, no hay archivos para servir. La validación que importa aquí es que `docker compose ps` muestre `app_php` como `Up` (sin "Restarting"). En el siguiente paso instalaremos Symfony y la página empezará a responder.

¡Excelente! Ya tienes Docker funcionando. Pero todavía no hay un proyecto Symfony. Eso lo haremos en el siguiente paso.

➡️ Continúa en [`02-instalar-symfony.md`](./02-instalar-symfony.md).
