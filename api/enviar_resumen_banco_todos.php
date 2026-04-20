<?php
/**
 * Envía el resumen por correo a todos los usuarios banco asignados a la solicitud
 * (un envío por cada fila en usuarios_banco_solicitudes).
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
    $stmt = $pdo->prepare(
        'SELECT usuario_banco_id FROM usuarios_banco_solicitudes WHERE solicitud_id = ? ORDER BY id'
    );
    $stmt->execute([$solicitud_id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($ids === false || count($ids) === 0) {
        echo json_encode(['success' => false, 'message' => 'No hay usuarios banco asignados a esta solicitud']);
        exit;
    }

    $ok = 0;
    $fallos = [];
    foreach ($ids as $uid) {
        $uid = (int) $uid;
        $resultado = enviarResumenSolicitudBanco($solicitud_id, $uid);
        if (!empty($resultado['success'])) {
            $ok++;
        } else {
            $fallos[] = [
                'usuario_banco_id' => $uid,
                'message' => $resultado['message'] ?? 'Error al enviar',
            ];
        }
    }

    $total = count($ids);
    if ($ok === $total) {
        echo json_encode([
            'success' => true,
            'message' => "Resumen enviado correctamente a {$ok} usuario(s).",
            'enviados' => $ok,
            'total' => $total,
            'fallos' => [],
        ]);
    } elseif ($ok > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Resumen enviado a {$ok} de {$total} usuario(s). Algunos envíos fallaron.",
            'enviados' => $ok,
            'total' => $total,
            'fallos' => $fallos,
            'partial' => true,
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo enviar el resumen a ningún usuario.',
            'enviados' => 0,
            'total' => $total,
            'fallos' => $fallos,
        ]);
    }
} catch (Throwable $e) {
    error_log('enviar_resumen_banco_todos: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al enviar los resúmenes']);
}
