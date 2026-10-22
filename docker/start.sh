#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    if [ "${APP_ENV:-production}" = "local" ]; then
        composer install --no-interaction --prefer-dist --no-progress
    else
        echo "ERROR: No se encontraron las dependencias de Composer."
        exit 1
    fi
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/mt2.conf
