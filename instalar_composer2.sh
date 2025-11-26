#!/bin/bash
# Script para actualizar Composer a la versión 2

echo "=== Actualizando Composer a la versión 2 ==="

# Opción 1: Intentar actualizar con self-update
echo "Intentando actualizar Composer con self-update..."
COMPOSER_ALLOW_SUPERUSER=1 composer self-update --2

# Verificar si se actualizó
COMPOSER_VERSION=$(COMPOSER_ALLOW_SUPERUSER=1 composer --version 2>&1 | head -n 1)
echo "Versión actual: $COMPOSER_VERSION"

# Si aún es versión 1, reinstalar
if [[ $COMPOSER_VERSION == *"Composer 1"* ]]; then
    echo "Composer aún es versión 1, reinstalando..."
    
    # Descargar e instalar Composer 2
    cd /tmp
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer --2
    php -r "unlink('composer-setup.php');"
    
    # Dar permisos
    chmod +x /usr/local/bin/composer
    
    echo "Composer 2 instalado en /usr/local/bin/composer"
fi

# Verificar versión final
echo ""
echo "=== Verificación Final ==="
COMPOSER_ALLOW_SUPERUSER=1 composer --version

echo ""
echo "=== Instalando dependencias ==="
cd /home/ubuntu/motus/baes
COMPOSER_ALLOW_SUPERUSER=1 composer install

echo ""
echo "=== Verificando instalación ==="
if [ -d "vendor/phpmailer" ]; then
    echo "✅ PHPMailer instalado correctamente"
    ls -la vendor/phpmailer/
else
    echo "❌ PHPMailer no se instaló"
fi

