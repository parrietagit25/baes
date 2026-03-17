<?php
/**
 * Envía por correo al usuario banco un resumen completo de la solicitud
 * (datos generales, perfil financiero, datos del auto, análisis, adjuntos).
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$solicitud_id = isset($_POST['solicitud_id']) ? (int) $_POST['solicitud_id'] : null;
$usuario_banco_id = isset($_POST['usuario_banco_id']) ? (int) $_POST['usuario_banco_id'] : null;

if (!$solicitud_id || !$usuario_banco_id) {
    echo json_encode(['success' => false, 'message' => 'Faltan solicitud_id o usuario_banco_id']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_helper.php';

try {
    $resultado = enviarResumenSolicitudBanco($solicitud_id, $usuario_banco_id);
    if ($resultado['success']) {
        echo json_encode(['success' => true, 'message' => 'Resumen enviado por correo correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => $resultado['message'] ?? 'Error al enviar']);
    }
} catch (Throwable $e) {
    error_log('enviar_resumen_banco: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al enviar el resumen']);
}
