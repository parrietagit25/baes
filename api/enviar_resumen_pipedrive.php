<?php
/**
 * Envía resumen de solicitud únicamente al correo de PipeDrive.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$solicitud_id = isset($_POST['solicitud_id']) ? (int) $_POST['solicitud_id'] : 0;
if ($solicitud_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Falta solicitud_id']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_helper.php';

try {
    $resultado = enviarResumenSolicitudPipedriveDirecto($solicitud_id);
    echo json_encode([
        'success' => !empty($resultado['success']),
        'message' => $resultado['message'] ?? (!empty($resultado['success']) ? 'Enviado' : 'No se pudo enviar')
    ]);
} catch (Throwable $e) {
    error_log('enviar_resumen_pipedrive: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al enviar a PipeDrive']);
}
