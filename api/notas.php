<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['solicitud_id'])) {
            obtenerNotasSolicitud($_GET['solicitud_id']);
        } else {
            obtenerTodasLasNotas();
        }
        break;
        
    case 'POST':
        crearNota();
        break;
        
    case 'PUT':
        actualizarNota();
        break;
        
    case 'DELETE':
        eliminarNota();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerNotasSolicitud($solicitudId) {
    global $pdo;
    
    try {
        // Verificar que el usuario tenga acceso a la solicitud
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.gestor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        // Verificar permisos
        $userRoles = $_SESSION['user_roles'];
        $tieneAcceso = false;
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_GESTOR', $userRoles) && $solicitud['gestor_id'] == $_SESSION['user_id']) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            $tieneAcceso = true;
        }
        
        if (!$tieneAcceso) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene acceso a esta solicitud']);
            return;
        }
        
        // Obtener notas
        $stmt = $pdo->prepare("
            SELECT n.*, u.nombre, u.apellido, u.email
            FROM notas_solicitud n
            LEFT JOIN usuarios u ON n.usuario_id = u.id
            WHERE n.solicitud_id = ?
            ORDER BY n.fecha_creacion DESC
        ");
        $stmt->execute([$solicitudId]);
        $notas = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $notas]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerTodasLasNotas() {
    global $pdo;
    
    try {
        $userRoles = $_SESSION['user_roles'];
        
        // Construir query según el rol
        $sql = "
            SELECT n.*, u.nombre, u.apellido, u.email, s.nombre_cliente, s.cedula
            FROM notas_solicitud n
            LEFT JOIN usuarios u ON n.usuario_id = u.id
            LEFT JOIN solicitudes_credito s ON n.solicitud_id = s.id
        ";
        
        $whereClause = "";
        $params = [];
        
        if (in_array('ROLE_GESTOR', $userRoles)) {
            $whereClause = " WHERE s.gestor_id = ?";
            $params[] = $_SESSION['user_id'];
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            $whereClause = " WHERE s.respuesta_banco IN ('Pendiente', 'Pre Aprobado')";
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            // Los administradores ven todas las notas
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }
        
        $sql .= $whereClause . " ORDER BY n.fecha_creacion DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notas = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $notas]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function crearNota() {
    global $pdo;
    
    try {
        // Validar campos requeridos
        if (empty($_POST['solicitud_id']) || empty($_POST['contenido'])) {
            echo json_encode(['success' => false, 'message' => 'Solicitud ID y contenido son requeridos']);
            return;
        }
        
        $solicitudId = $_POST['solicitud_id'];
        
        // Verificar que la solicitud existe y el usuario tiene acceso
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.gestor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        // Verificar permisos
        $userRoles = $_SESSION['user_roles'];
        $tieneAcceso = false;
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_GESTOR', $userRoles) && $solicitud['gestor_id'] == $_SESSION['user_id']) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            $tieneAcceso = true;
        }
        
        if (!$tieneAcceso) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene acceso a esta solicitud']);
            return;
        }
        
        // Crear nota
        $stmt = $pdo->prepare("
            INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido, archivo_adjunto)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $solicitudId,
            $_SESSION['user_id'],
            $_POST['tipo_nota'] ?? 'Comentario',
            $_POST['titulo'] ?? '',
            $_POST['contenido'],
            $_POST['archivo_adjunto'] ?? null
        ]);
        
        $notaId = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'message' => 'Nota creada correctamente', 'id' => $notaId]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear nota']);
    }
}

function actualizarNota() {
    global $pdo;
    
    try {
        // Obtener datos del PUT request
        parse_str(file_get_contents("php://input"), $_PUT);
        
        if (empty($_PUT['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de nota requerido']);
            return;
        }
        
        // Verificar que la nota existe y el usuario es el autor
        $stmt = $pdo->prepare("
            SELECT n.*, s.gestor_id
            FROM notas_solicitud n
            LEFT JOIN solicitudes_credito s ON n.solicitud_id = s.id
            WHERE n.id = ?
        ");
        $stmt->execute([$_PUT['id']]);
        $nota = $stmt->fetch();
        
        if (!$nota) {
            echo json_encode(['success' => false, 'message' => 'Nota no encontrada']);
            return;
        }
        
        // Verificar permisos (solo el autor o admin puede editar)
        $userRoles = $_SESSION['user_roles'];
        $puedeEditar = false;
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $puedeEditar = true;
        } elseif ($nota['usuario_id'] == $_SESSION['user_id']) {
            $puedeEditar = true;
        }
        
        if (!$puedeEditar) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para editar esta nota']);
            return;
        }
        
        // Actualizar nota
        $campos = [];
        $valores = [];
        
        $camposPermitidos = ['titulo', 'contenido', 'archivo_adjunto'];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($_PUT[$campo])) {
                $campos[] = "$campo = ?";
                $valores[] = $_PUT[$campo];
            }
        }
        
        if (!empty($campos)) {
            $valores[] = $_PUT['id'];
            $sql = "UPDATE notas_solicitud SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
        }
        
        echo json_encode(['success' => true, 'message' => 'Nota actualizada correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar nota']);
    }
}

function eliminarNota() {
    global $pdo;
    
    try {
        // Obtener datos del DELETE request
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        if (empty($_DELETE['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de nota requerido']);
            return;
        }
        
        // Verificar que la nota existe y el usuario es el autor
        $stmt = $pdo->prepare("
            SELECT n.*, s.gestor_id
            FROM notas_solicitud n
            LEFT JOIN solicitudes_credito s ON n.solicitud_id = s.id
            WHERE n.id = ?
        ");
        $stmt->execute([$_DELETE['id']]);
        $nota = $stmt->fetch();
        
        if (!$nota) {
            echo json_encode(['success' => false, 'message' => 'Nota no encontrada']);
            return;
        }
        
        // Verificar permisos (solo el autor o admin puede eliminar)
        $userRoles = $_SESSION['user_roles'];
        $puedeEliminar = false;
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $puedeEliminar = true;
        } elseif ($nota['usuario_id'] == $_SESSION['user_id']) {
            $puedeEliminar = true;
        }
        
        if (!$puedeEliminar) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar esta nota']);
            return;
        }
        
        // Eliminar nota
        $stmt = $pdo->prepare("DELETE FROM notas_solicitud WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Nota eliminada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nota no encontrada']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar nota']);
    }
}
?>
