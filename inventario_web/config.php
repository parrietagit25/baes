<?php
/**
 * Configuración del módulo inventario_web (datos desde Python).
 * Usa la misma base de datos que el proyecto (config/database.php).
 */

require_once __DIR__ . '/../config/database.php';

// Token para autorizar las peticiones desde Python (cambiar en producción)
if (!defined('INVENTARIO_WEB_TOKEN')) {
    define('INVENTARIO_WEB_TOKEN', getenv('INVENTARIO_WEB_TOKEN') ?: 'SI5dGxz/2/AqWkOYuz6t4r3KYGbqGxOj3MhT3T/hp!J6Du9ko=6ITrMBNJU5WzUj?ep3VWb8gwxGv9RPgq?r0y=A8gdF2cJ!fWil1G??6voWqJvRdip1M?0u/sol-ON?');
}

// Ruta del log (dentro del subdirectorio o logs del proyecto)
define('INVENTARIO_WEB_LOG', __DIR__ . '/script_log_desde_python.txt');
