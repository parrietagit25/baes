<?php
/**
 * Configuración del módulo financiamiento (acceso a ver registros y formulario).
 */

// El formulario que llena el cliente (index.php) es público y no usa esta clave.
// El acceso interno a registros, si se habilita, debe salir del entorno.
$finAdminPass = getenv('FINANCIAMIENTO_ADMIN_PASSWORD');
if (!defined('FINANCIAMIENTO_ADMIN_PASSWORD') && is_string($finAdminPass) && $finAdminPass !== '') {
    define('FINANCIAMIENTO_ADMIN_PASSWORD', $finAdminPass);
}

/**
 * URL de la API donde el formulario envía los datos (solicitud_publica.php).
 * Deje vacío '' para que se calcule automáticamente según la ruta del formulario.
 * En servidor (GoDaddy, etc.) puede fijar la URL completa para evitar 404, por ejemplo:
 *   define('FINANCIAMIENTO_API_URL', 'https://grupopcr.com.pa/solicitud_credito/api/solicitud_publica.php');
 */
if (!defined('FINANCIAMIENTO_API_URL')) {
    define('FINANCIAMIENTO_API_URL', '');
}
