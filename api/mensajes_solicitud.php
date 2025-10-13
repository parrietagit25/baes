<?php
/**
 * API para gestionar mensajes del muro de solicitudes
 */

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$solicitud_id = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : null;

try {
    switch ($method) {
        case 'GET':
            obtenerMensajes($pdo, $solicitud_id);
            break;
            
        case 'POST':
            enviarMensaje($pdo);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

/**
 * Obtener mensajes de una solicitud
 */
function obtenerMensajes($pdo, $solicitud_id) {
    if (!$solicitud_id) {
        echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
        return;
    }
    
    $sql = "
        SELECT m.*, 
               u.nombre, u.apellido,
               r.nombre as rol_nombre
        FROM mensajes_solicitud m
        JOIN usuarios u ON m.usuario_id = u.id
        LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
        LEFT JOIN roles r ON ur.rol_id = r.id
        WHERE m.solicitud_id = ?
        ORDER BY m.fecha_creacion ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$solicitud_id]);
    $mensajes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $mensajes]);
}

/**
 * Enviar mensaje
 */
function enviarMensaje($pdo) {
    $solicitud_id = $_POST['solicitud_id'] ?? null;
    $mensaje = trim($_POST['mensaje'] ?? '');
    $tipo = $_POST['tipo'] ?? 'general';
    
    if (!$solicitud_id || empty($mensaje)) {
        echo json_encode(['success' => false, 'message' => 'Datos requeridos faltantes']);
        return;
    }
    
    if (!in_array($tipo, ['general', 'banco', 'gestor'])) {
        $tipo = 'general';
    }
    
    // Determinar tipo basado en el rol del usuario
    $sql_rol = "
        SELECT r.nombre as rol_nombre
        FROM usuarios u
        JOIN usuario_roles ur ON u.id = ur.usuario_id
        JOIN roles r ON ur.rol_id = r.id
        WHERE u.id = ?
    ";
    $stmt = $pdo->prepare($sql_rol);
    $stmt->execute([$_SESSION['user_id']]);
    $rol = $stmt->fetch();
    
    if ($rol) {
        if ($rol['rol_nombre'] === 'ROLE_BANCO') {
            $tipo = 'banco';
        } elseif (in_array($rol['rol_nombre'], ['ROLE_GESTOR', 'ROLE_ADMIN'])) {
            $tipo = 'gestor';
        }
    }
    
    $sql = "
        INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo)
        VALUES (?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$solicitud_id, $_SESSION['user_id'], $mensaje, $tipo]);
    
    echo json_encode(['success' => true, 'message' => 'Mensaje enviado correctamente']);
}
?>
