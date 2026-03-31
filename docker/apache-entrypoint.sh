#!/bin/sh
set -e
# Con volumen ./:/var/www/html el vendor de la imagen se oculta: instalar deps en el bind mount si falta Resend.
cd /var/www/html
if [ -f composer.json ] && { [ ! -f vendor/autoload.php ] || [ ! -d vendor/resend ]; }; then
    if command -v composer >/dev/null 2>&1; then
        composer install --no-dev --no-interaction --optimize-autoloader \
            || composer update --no-dev --no-interaction --optimize-autoloader
    fi
fi
exec /usr/local/bin/docker-php-entrypoint "$@"
