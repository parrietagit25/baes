<?php
/**
 * Configuración del módulo financiamiento (acceso a ver registros y formulario).
 */

// Contraseña para acceder a ver_registros.php. Cámbiela por una segura.
define('FINANCIAMIENTO_ADMIN_PASSWORD', 'admin123');

/**
 * URL de la API donde el formulario envía los datos (solicitud_publica.php).
 * Deje vacío '' para que se calcule automáticamente según la ruta del formulario.
 * En servidor (GoDaddy, etc.) puede fijar la URL completa para evitar 404, por ejemplo:
 *   define('FINANCIAMIENTO_API_URL', 'https://grupopcr.com.pa/solicitud_credito/api/solicitud_publica.php');
 */
if (!defined('FINANCIAMIENTO_API_URL')) {
    define('FINANCIAMIENTO_API_URL', '');
}
