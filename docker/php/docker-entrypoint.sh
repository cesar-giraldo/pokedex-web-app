#!/bin/sh
set -e

# El ini compartido no muestra errores. Este script elige el archivo del entorno
# antes de arrancar FrankenPHP, para que producción no enseñe trazas en el navegador.
if [ "${APP_ENV}" = "prod" ]; then
    if [ -z "${APP_SECRET}" ] || [ "${APP_SECRET}" = "ChangeMe!ThisIsADevSecret" ]; then
        echo "APP_SECRET debe definirse en el entorno del servidor cuando APP_ENV=prod." >&2
        exit 1
    fi
    cp /usr/local/etc/php/env/prod.ini /usr/local/etc/php/conf.d/zz-env.ini
else
    cp /usr/local/etc/php/env/dev.ini /usr/local/etc/php/conf.d/zz-env.ini
fi

exec docker-php-entrypoint "$@"
