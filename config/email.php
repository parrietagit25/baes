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
function getEnvOrDefault($key, $default) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// Cargar configuración local si existe (no está en git)
$localConfigPath = __DIR__ . '/email.local.php';
if (file_exists($localConfigPath)) {
    return require $localConfigPath;
}

// Configuración por defecto (usa variables de entorno)
return [
    // Configuración SendGrid API
    // IMPORTANTE: La API key debe configurarse como variable de entorno SENDGRID_API_KEY
    // O crear un archivo .env.local (que está en .gitignore)
    'sendgrid_api_key' => getEnvOrDefault('SENDGRID_API_KEY', ''),
    
    // Configuración del remitente (verificado en SendGrid)
    'from_email' => getEnvOrDefault('SENDGRID_FROM_EMAIL', 'noreply@automarketrentacar.com'),
    'from_name' => getEnvOrDefault('SENDGRID_FROM_NAME', 'Automarket Rent a Car'),
    
    // Configuración adicional
    'reply_to_email' => getEnvOrDefault('SENDGRID_REPLY_TO', 'noreply@automarketrentacar.com'),
    'reply_to_name' => getEnvOrDefault('SENDGRID_REPLY_TO_NAME', 'Automarket Rent a Car - Soporte'),
    
    // Configuración de la aplicación
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'Automarket Rent a Car',
    
    // Debug (desactivar en producción)
    'debug' => getEnvOrDefault('SENDGRID_DEBUG', 'false') === 'true',
];

