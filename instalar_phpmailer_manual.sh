#!/bin/bash
# Script para instalar PHPMailer manualmente sin Composer

echo "=== Instalando PHPMailer manualmente ==="

cd /home/ubuntu/motus/baes

# Crear estructura de directorios
mkdir -p vendor/phpmailer/phpmailer/src
mkdir -p vendor/composer

# Descargar PHPMailer
cd vendor/phpmailer/phpmailer
echo "Descargando PHPMailer..."
wget -q https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip -O phpmailer.zip

if [ -f phpmailer.zip ]; then
    echo "Extrayendo PHPMailer..."
    unzip -q phpmailer.zip
    mv PHPMailer-6.9.1/src/* src/
    mv PHPMailer-6.9.1/language src/
    mv PHPMailer-6.9.1/LICENSE LICENSE
    rm -rf PHPMailer-6.9.1 phpmailer.zip
    
    echo "✅ PHPMailer descargado e instalado"
else
    echo "❌ Error al descargar PHPMailer"
    exit 1
fi

# Crear autoload básico
cd /home/ubuntu/motus/baes
cat > vendor/autoload.php << 'EOF'
<?php
// Autoload básico para PHPMailer
spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
EOF

echo "✅ Autoload creado"
echo ""
echo "=== Verificación ==="
if [ -f "vendor/autoload.php" ] && [ -d "vendor/phpmailer/phpmailer/src" ]; then
    echo "✅ Instalación manual completada"
    echo "Archivos instalados:"
    ls -la vendor/phpmailer/phpmailer/src/ | head -5
else
    echo "❌ Error en la instalación"
fi

