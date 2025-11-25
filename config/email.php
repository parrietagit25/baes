<?php
/**
 * Configuración SMTP para envío de correos
 * 
 * IMPORTANTE: Configurar estas variables según tu servidor SMTP
 * Puedes usar servicios como Gmail, SendGrid, Mailgun, etc.
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

return [
    // Configuración SMTP - Outlook
    'smtp_host' => getEnvOrDefault('SMTP_HOST', 'smtp-mail.outlook.com'),
    'smtp_port' => (int)getEnvOrDefault('SMTP_PORT', '587'),
    'smtp_secure' => getEnvOrDefault('SMTP_SECURE', 'tls'), // 'tls' o 'ssl'
    'smtp_username' => getEnvOrDefault('SMTP_USERNAME', 'notificaciones@grupopcr.com.pa'),
    'smtp_password' => getEnvOrDefault('SMTP_PASSWORD', 'R>xv7A=u[3WnJ{rDg;#S'),
    
    // Configuración del remitente
    'from_email' => getEnvOrDefault('SMTP_FROM_EMAIL', 'notificaciones@grupopcr.com.pa'),
    'from_name' => getEnvOrDefault('SMTP_FROM_NAME', 'Sistema BAES - Grupo PCR'),
    
    // Configuración adicional
    'reply_to_email' => getEnvOrDefault('SMTP_REPLY_TO', 'notificaciones@grupopcr.com.pa'),
    'reply_to_name' => getEnvOrDefault('SMTP_REPLY_TO_NAME', 'Soporte BAES - Grupo PCR'),
    
    // Configuración de la aplicación
    'app_url' => getEnvOrDefault('APP_URL', 'http://localhost:8086'),
    'app_name' => 'Sistema BAES',
    
    // Debug (desactivar en producción)
    'debug' => getEnvOrDefault('SMTP_DEBUG', 'false') === 'true',
];

