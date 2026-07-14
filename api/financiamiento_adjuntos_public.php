<?php
/**
 * API pública JSON (opcional). La subida principal del cliente usa POST clásico en
 * financiamiento/solicitar_adjuntos.php para evitar 403 de Cloudflare en fetch multipart.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/configuracion_sistema_helper.php';

if (motus_mantenimiento_activo()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => motus_mantenimiento_mensaje(), 'maintenance' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/financiamiento_adjuntos_public_lib.php';

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Base de datos no disponible.']);
        exit;
    }
    if (!finAdjTok_tablaExiste($pdo)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Función no disponible: ejecute database/migracion_financiamiento_adjuntos_token.sql']);
        exit;
    }

    $token = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
    } else {
        $token = isset($_POST['t']) ? trim((string) $_POST['t']) : '';
        if ($token === '' && isset($_GET['t'])) {
            $token = trim((string) $_GET['t']);
        }
    }

    $ctx = finAdjTok_resolver($pdo, $token);
    if ($ctx === null) {
        http_response_code(410);
        echo json_encode(['success' => false, 'message' => 'El enlace no es válido, fue reemplazado o ya caducó.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => [
                'cliente_nombre' => $ctx['cliente_nombre'],
                'expires_at' => $ctx['expires_at'],
                'adjuntos' => finAdjTok_listarAdjuntos($pdo, $ctx['fr_id']),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $resultado = finAdjTok_procesarSubida($pdo, $ctx, finAdjTok_filesFromRequest());
    if (empty($resultado['success'])) {
        http_response_code(400);
    }
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('financiamiento_adjuntos_public: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.']);
}
