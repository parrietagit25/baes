#!/bin/bash
# Script para instalar y habilitar cURL en PHP

echo "=== Instalando extensión cURL de PHP ==="

# Detectar versión de PHP
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
echo "Versión de PHP detectada: $PHP_VERSION"

# Instalar cURL
echo "Instalando php-curl..."
apt-get update
apt-get install -y php-curl

# O si es PHP específico
if command -v php${PHP_VERSION}-curl &> /dev/null; then
    apt-get install -y php${PHP_VERSION}-curl
fi

# Reiniciar servicios si es necesario
if systemctl is-active --quiet apache2; then
    echo "Reiniciando Apache..."
    systemctl restart apache2
elif systemctl is-active --quiet php-fpm; then
    echo "Reiniciando PHP-FPM..."
    systemctl restart php-fpm
fi

# Verificar instalación
echo ""
echo "=== Verificación ==="
php -m | grep -i curl

if php -m | grep -i curl > /dev/null; then
    echo "✅ cURL está habilitado"
else
    echo "❌ cURL NO está habilitado"
    echo "Intenta: php -r \"var_dump(extension_loaded('curl'));\""
fi

echo ""
echo "=== Proceso completado ==="

