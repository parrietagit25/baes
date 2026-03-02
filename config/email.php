<?php
/**
 * Configuración SendGrid para envío de correos
 * 
 * IMPORTANTE: Configurar estas variables según tu cuenta de SendGrid
 * 
 * Puedes configurar estas variables de dos formas:
 * 1. Modificando directamente este archivo
 * 2. Usando variables de entorno (getenv)
 */

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
    'from_email' => getEnvOrDefault('SENDGRID_FROM_EMAIL', 'noreply@automarketrentacar.com'),
    'from_name' => getEnvOrDefault('SENDGRID_FROM_NAME', 'AutoMarket Seminuevos'),
    'reply_to_email' => getEnvOrDefault('SENDGRID_REPLY_TO', 'noreply@automarketrentacar.com'),
    'reply_to_name' => getEnvOrDefault('SENDGRID_REPLY_TO_NAME', 'AutoMarket Seminuevos - Soporte'),
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'AutoMarket Seminuevos',
    'debug' => getEnvOrDefault('SENDGRID_DEBUG', 'false') === 'true',
];

