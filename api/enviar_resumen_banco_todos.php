<?php
/**
 * Envía un único correo de resumen usando CCO para todos los usuarios banco asignados.
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
    $resultado = enviarResumenSolicitudBancoTodosUnCorreo($solicitud_id);
    if (!empty($resultado['success'])) {
        $enviados = isset($resultado['enviados']) ? (int) $resultado['enviados'] : 0;
        $msg = $resultado['message'] ?? "Resumen enviado correctamente en un solo correo (CCO) a {$enviados} usuario(s) banco.";
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'enviados' => $enviados,
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $resultado['message'] ?? 'No se pudo enviar el resumen.',
        ]);
    }
} catch (Throwable $e) {
    error_log('enviar_resumen_banco_todos: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al enviar los resúmenes']);
}
