#!/bin/sh
set -e
# El bind mount trae la config nueva sin reconstruir la imagen.
if [ -f /var/www/html/docker/php-seguridad.ini ]; then
    cp /var/www/html/docker/php-seguridad.ini /usr/local/etc/php/conf.d/zz-seguridad.ini
fi
if [ -f /var/www/html/docker/apache-seguridad.conf ]; then
    cp /var/www/html/docker/apache-seguridad.conf /etc/apache2/conf-enabled/seguridad.conf
fi
a2enmod headers expires deflate rewrite >/dev/null 2>&1 || true
# Con volumen ./:/var/www/html el vendor de la imagen se oculta: instalar deps en el bind mount si falta Resend.
cd /var/www/html
if [ -f composer.json ] && { [ ! -f vendor/autoload.php ] || [ ! -d vendor/resend ]; }; then
    if command -v composer >/dev/null 2>&1; then
        composer install --no-dev --no-interaction --optimize-autoloader \
            || composer update --no-dev --no-interaction --optimize-autoloader
    fi
fi
exec /usr/local/bin/docker-php-entrypoint "$@"
