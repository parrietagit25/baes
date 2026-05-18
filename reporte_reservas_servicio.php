<?php
/**
 * Punto de entrada alternativo para reportes de reservas (fuera de /api/).
 * Evita el desafío de Cloudflare que a veces bloquea POST/GET a /api/*.
 */
require __DIR__ . '/api/reporte_reservas.php';
