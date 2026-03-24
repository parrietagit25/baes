<?php
/**
 * Configuración SendGrid para envío de correos
 * 
 * IMPORTANTE: Configurar estas variables según tu cuenta de SendGrid
 * 
 * Puedes configurar estas variables de dos formas:
 * 1. Modificando directamente este archivo
 * 2. Usando variables de entorno (getenv)
 * 3. Archivo .env en la raíz del proyecto (misma carpeta que composer.json)
 */

// Cargar .env si existe (Apache/PHP no lo leen solos; igual que config/chatbot.php)
$__emailEnvFile = __DIR__ . '/../.env';
if (is_file($__emailEnvFile) && is_readable($__emailEnvFile)) {
    $__lines = @file($__emailEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($__lines) {
        foreach ($__lines as $__line) {
            $__line = trim($__line);
            if ($__line === '' || strpos($__line, '#') === 0) {
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $__line, $__m)) {
                $__key = $__m[1];
                $__val = trim($__m[2]);
                if (strpos($__val, '"') === 0 && substr($__val, -1) === '"') {
                    $__val = substr($__val, 1, -1);
                } elseif (strpos($__val, "'") === 0 && substr($__val, -1) === "'") {
                    $__val = substr($__val, 1, -1);
                }
                if (getenv($__key) === false) {
                    putenv("$__key=$__val");
                    $_ENV[$__key] = $__val;
                }
            }
        }
    }
}
unset($__emailEnvFile, $__lines, $__line, $__m, $__key, $__val);

// Función helper para obtener variables de entorno o valores por defecto
if (!function_exists('getEnvOrDefault')) {
    function getEnvOrDefault($key, $default) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

// Cargar configuración local si existe (no está en git)
$localConfigPath = __DIR__ . '/email.local.php';
if (file_exists($localConfigPath)) {
    return require $localConfigPath;
}

// Configuración por defecto (usa variables de entorno)
return [
    // Método de envío: 'smtp' (Outlook/Office365) o 'sendgrid'. Si smtp_host está definido se usa SMTP.
    'driver' => getEnvOrDefault('EMAIL_DRIVER', 'sendgrid'),
    'sendgrid_api_key' => getEnvOrDefault('SENDGRID_API_KEY', ''),
    'smtp_host' => getEnvOrDefault('SMTP_HOST', ''),
    'smtp_port' => (int) getEnvOrDefault('SMTP_PORT', '587'),
    'smtp_user' => getEnvOrDefault('SMTP_USER', ''),
    'smtp_pass' => getEnvOrDefault('SMTP_PASS', ''),
    'smtp_secure' => getEnvOrDefault('SMTP_SECURE', 'tls'),
    // Evitar 504 de Cloudflare si el servidor no alcanza el SMTP (PHPMailer por defecto ~300s)
    'smtp_timeout' => (int) getEnvOrDefault('SMTP_TIMEOUT', '25'),
    'from_email' => getEnvOrDefault('SENDGRID_FROM_EMAIL', 'noreply.automarket@automarket.com.pa'),
    'from_name' => getEnvOrDefault('SENDGRID_FROM_NAME', 'AutoMarket'),
    'reply_to_email' => getEnvOrDefault('SENDGRID_REPLY_TO', 'noreply.automarket@automarket.com.pa'),
    'reply_to_name' => getEnvOrDefault('SENDGRID_REPLY_TO_NAME', 'AutoMarket - Soporte'),
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'AutoMarket Seminuevos',
    'debug' => getEnvOrDefault('SENDGRID_DEBUG', 'false') === 'true',
];

