<?php
/**
 * API para gestionar usuarios banco asignados a solicitudes
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
            if ($solicitud_id) {
                obtenerUsuariosAsignados($pdo, $solicitud_id);
            } else {
                buscarUsuariosBanco($pdo);
            }
            break;
            
        case 'POST':
            asignarUsuarioBanco($pdo);
            break;
            
        case 'PUT':
            actualizarEstadoUsuario($pdo);
            break;
            
        case 'DELETE':
            desasignarUsuario($pdo);
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
 * Obtener usuarios banco asignados a una solicitud
 */
function obtenerUsuariosAsignados($pdo, $solicitud_id) {
    $sql = "
        SELECT ubs.*, 
               u.nombre as usuario_nombre, 
               u.apellido as usuario_apellido, 
               u.email as usuario_email,
               u.telefono as usuario_telefono,
               b.nombre as banco_nombre,
               u_creador.nombre as creado_por_nombre, 
               u_creador.apellido as creado_por_apellido
        FROM usuarios_banco_solicitudes ubs
        JOIN usuarios u ON ubs.usuario_banco_id = u.id
        LEFT JOIN bancos b ON u.banco_id = b.id
        JOIN usuarios u_creador ON ubs.creado_por = u_creador.id
        WHERE ubs.solicitud_id = ?
        ORDER BY ubs.fecha_asignacion DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$solicitud_id]);
    $usuarios = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $usuarios]);
}

/**
 * Buscar usuarios banco para autocompletado
 */
function buscarUsuariosBanco($pdo) {
    $termino = $_GET['q'] ?? '';
    
    if (strlen($termino) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    
    $sql = "
        SELECT u.id, u.nombre, u.apellido, u.email,
               b.nombre as banco_nombre
        FROM usuarios u
        LEFT JOIN bancos b ON u.banco_id = b.id
        JOIN usuario_roles ur ON u.id = ur.usuario_id
        JOIN roles r ON ur.rol_id = r.id
        WHERE r.nombre = 'ROLE_BANCO' 
        AND u.activo = 1
        AND (u.nombre LIKE ? OR u.apellido LIKE ? OR b.nombre LIKE ?)
        ORDER BY b.nombre, u.nombre
        LIMIT 10
    ";
    
    $termino_busqueda = "%{$termino}%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$termino_busqueda, $termino_busqueda, $termino_busqueda]);
    $usuarios = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $usuarios]);
}

/**
 * Asignar usuario banco a una solicitud
 */
function asignarUsuarioBanco($pdo) {
    $solicitud_id = $_POST['solicitud_id'] ?? null;
    $usuario_banco_id = $_POST['usuario_banco_id'] ?? null;
    
    if (!$solicitud_id || !$usuario_banco_id) {
        echo json_encode(['success' => false, 'message' => 'Datos requeridos faltantes']);
        return;
    }
    
    // Verificar que el usuario tenga rol ROLE_BANCO
    $sql_verificar = "
        SELECT u.id FROM usuarios u
        JOIN usuario_roles ur ON u.id = ur.usuario_id
        JOIN roles r ON ur.rol_id = r.id
        WHERE u.id = ? AND r.nombre = 'ROLE_BANCO' AND u.activo = 1
    ";
    $stmt = $pdo->prepare($sql_verificar);
    $stmt->execute([$usuario_banco_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El usuario no tiene rol de banco o está inactivo']);
        return;
    }
    
    // Verificar que no esté ya asignado
    $sql_existe = "SELECT id FROM usuarios_banco_solicitudes WHERE solicitud_id = ? AND usuario_banco_id = ?";
    $stmt = $pdo->prepare($sql_existe);
    $stmt->execute([$solicitud_id, $usuario_banco_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El usuario ya está asignado a esta solicitud']);
        return;
    }
    
    // Asignar usuario
    $sql_insert = "
        INSERT INTO usuarios_banco_solicitudes (solicitud_id, usuario_banco_id, creado_por)
        VALUES (?, ?, ?)
    ";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute([$solicitud_id, $usuario_banco_id, $_SESSION['user_id']]);
    
    // Actualizar estado de la solicitud a "En Revisión Banco"
    $sql_update_estado = "
        UPDATE solicitudes_credito 
        SET estado = 'En Revisión Banco', 
            fecha_actualizacion = NOW()
        WHERE id = ? AND estado = 'Nueva'
    ";
    $stmt = $pdo->prepare($sql_update_estado);
    $stmt->execute([$solicitud_id]);
    
    // Obtener información del usuario banco asignado para la nota
    $sql_usuario_info = "
        SELECT u.nombre, u.apellido, b.nombre as banco_nombre
        FROM usuarios u
        LEFT JOIN bancos b ON u.banco_id = b.id
        WHERE u.id = ?
    ";
    $stmt = $pdo->prepare($sql_usuario_info);
    $stmt->execute([$usuario_banco_id]);
    $usuario_info = $stmt->fetch();
    
    // Crear nota automática del cambio de estado
    $sql_nota = "
        INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
        VALUES (?, ?, 'Actualización', 'Solicitud enviada a revisión bancaria', ?)
    ";
    
    $contenido_nota = "Solicitud asignada al usuario banco: {$usuario_info['nombre']} {$usuario_info['apellido']}";
    if ($usuario_info['banco_nombre']) {
        $contenido_nota .= " ({$usuario_info['banco_nombre']})";
    }
    $contenido_nota .= ". Estado cambiado a 'En Revisión Banco'.";
    
    $stmt = $pdo->prepare($sql_nota);
    $stmt->execute([$solicitud_id, $_SESSION['user_id'], $contenido_nota]);
    
    echo json_encode(['success' => true, 'message' => 'Usuario asignado correctamente y solicitud enviada a revisión bancaria']);
}

/**
 * Actualizar estado de usuario (activar/desactivar)
 */
function actualizarEstadoUsuario($pdo) {
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? null;
    
    if (!$id || !in_array($estado, ['activo', 'inactivo'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }
    
    $fecha_campo = $estado === 'inactivo' ? 'fecha_desactivacion = NOW()' : 'fecha_desactivacion = NULL';
    
    $sql = "UPDATE usuarios_banco_solicitudes SET estado = ?, {$fecha_campo} WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$estado, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
}

/**
 * Desasignar usuario (eliminar asignación)
 */
function desasignarUsuario($pdo) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    $sql = "DELETE FROM usuarios_banco_solicitudes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Usuario desasignado correctamente']);
}
?>
