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
# ENV FRANKENPHP_CONFIG="worker ./public/index.php"

# 8. Exponer puertos HTTP/HTTPS
EXPOSE 80
EXPOSE 443
EXPOSE 443/udp