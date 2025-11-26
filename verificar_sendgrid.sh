#!/bin/bash
# Script para verificar la configuración de SendGrid

echo "=== Verificando configuración de SendGrid ==="
echo ""

cd /home/ubuntu/motus/baes

echo "1. Verificando archivos de configuración..."
if [ -f "config/email.local.php" ]; then
    echo "✅ config/email.local.php existe"
else
    echo "❌ config/email.local.php NO existe"
    echo "   Ejecuta: ./configurar_sendgrid_servidor.sh"
    exit 1
fi

echo ""
echo "2. Verificando SendGrid instalado..."
if [ -d "vendor/sendgrid" ]; then
    echo "✅ SendGrid está instalado"
else
    echo "❌ SendGrid NO está instalado"
    echo "   Ejecuta: composer install"
    exit 1
fi

echo ""
echo "3. Verificando autoload..."
if [ -f "vendor/autoload.php" ]; then
    echo "✅ vendor/autoload.php existe"
else
    echo "❌ vendor/autoload.php NO existe"
    exit 1
fi

echo ""
echo "4. Probando carga de configuración..."
php -r "
require 'vendor/autoload.php';
\$config = require 'config/email.php';
if (!empty(\$config['sendgrid_api_key'])) {
    echo '✅ Configuración cargada correctamente';
    echo '   API Key: ' . substr(\$config['sendgrid_api_key'], 0, 10) . '...';
    echo '   From: ' . \$config['from_email'];
} else {
    echo '❌ API Key no configurada';
    exit(1);
}
"

echo ""
echo "5. Probando EmailService..."
php -r "
require 'vendor/autoload.php';
require 'config/email.php';
require 'includes/EmailService.php';
try {
    \$service = new EmailService();
    echo '✅ EmailService inicializado correctamente';
} catch (Exception \$e) {
    echo '❌ Error: ' . \$e->getMessage();
    exit(1);
}
"

echo ""
echo "=== Verificación completada ==="

