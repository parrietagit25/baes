<?php
/**
 * Compatibilidad: Corredor también se expone vía api/adjuntos.php (action=corredor_*).
 * Preferir adjuntos.php: Cloudflare bloquea POST a este path.
 */
session_start();

$esDescarga = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && isset($_GET['id'])
    && isset($_GET['action'])
    && $_GET['action'] === 'descargar';

if (!$esDescarga) {
    header('Content-Type: application/json');
}

if (!isset($_SESSION['user_id'])) {
    if ($esDescarga) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/historial_helper.php';
require_once __DIR__ . '/../includes/corredor_api.php';

asegurarTablaCorredorEnvios($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        if ($esDescarga) {
            descargarEnvioCorredor($pdo, (int) $_GET['id']);
            exit;
        }
        $solicitudId = isset($_GET['solicitud_id']) ? (int) $_GET['solicitud_id'] : 0;
        if ($solicitudId <= 0) {
            echo json_encode(['success' => false, 'message' => 'solicitud_id requerido']);
            exit;
        }
        listarEnviosCorredor($pdo, $solicitudId);
        exit;
    }

    if ($method === 'POST') {
        enviarResumenCorredorApi($pdo);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    error_log('enviar_resumen_corredor: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al procesar Corredor']);
}
