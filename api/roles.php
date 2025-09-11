<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            obtenerRol($_GET['id']);
        } elseif (isset($_GET['validar_nombre'])) {
            validarNombreRol($_GET['validar_nombre'], $_GET['excluir_id'] ?? null);
        } elseif (isset($_GET['verificar_usuarios'])) {
            verificarUsuariosRol($_GET['verificar_usuarios']);
        } elseif (isset($_GET['usuarios_rol'])) {
            obtenerUsuariosRol($_GET['usuarios_rol']);
        } else {
            obtenerRoles();
        }
        break;
        
    case 'POST':
        crearRol();
        break;
        
    case 'PUT':
        actualizarRol();
        break;
        
    case 'DELETE':
        eliminarRol();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerRoles() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
        $roles = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $roles]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerRol($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $rol = $stmt->fetch();
        
        if ($rol) {
            echo json_encode(['success' => true, 'data' => $rol]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Rol no encontrado']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function validarNombreRol($nombre, $excluirId = null) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM roles WHERE nombre = ?";
        $params = [$nombre];
        
        if ($excluirId) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'disponible' => $count == 0]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function verificarUsuariosRol($rolId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_roles WHERE rol_id = ?");
        $stmt->execute([$rolId]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'usuarios_asignados' => $count]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerUsuariosRol($rolId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellido, u.email, u.activo
            FROM usuarios u
            INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
            WHERE ur.rol_id = ?
            ORDER BY u.nombre
        ");
        $stmt->execute([$rolId]);
        $usuarios = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $usuarios]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function crearRol() {
    global $pdo;
    
    try {
        // Validar datos requeridos
        if (empty($_POST['nombre'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre del rol es requerido']);
            return;
        }
        
        // Validar nombre único
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE nombre = ?");
        $stmt->execute([$_POST['nombre']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'El nombre del rol ya existe']);
            return;
        }
        
        // Insertar rol
        $stmt = $pdo->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['descripcion'] ?? null
        ]);
        
        $rolId = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'message' => 'Rol creado correctamente', 'id' => $rolId]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear rol']);
    }
}

function actualizarRol() {
    global $pdo;
    
    try {
        // Obtener datos del PUT request
        parse_str(file_get_contents("php://input"), $_PUT);
        
        if (empty($_PUT['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de rol requerido']);
            return;
        }
        
        // Verificar si el rol existe
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$_PUT['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Rol no encontrado']);
            return;
        }
        
        // Validar nombre único (excluyendo el rol actual)
        if (!empty($_PUT['nombre'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE nombre = ? AND id != ?");
            $stmt->execute([$_PUT['nombre'], $_PUT['id']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El nombre del rol ya existe']);
                return;
            }
        }
        
        // Construir query de actualización
        $campos = [];
        $valores = [];
        
        if (isset($_PUT['nombre'])) {
            $campos[] = "nombre = ?";
            $valores[] = $_PUT['nombre'];
        }
        
        if (isset($_PUT['descripcion'])) {
            $campos[] = "descripcion = ?";
            $valores[] = $_PUT['descripcion'];
        }
        
        if (isset($_PUT['activo'])) {
            $campos[] = "activo = ?";
            $valores[] = $_PUT['activo'] ? 1 : 0;
        }
        
        if (!empty($campos)) {
            $valores[] = $_PUT['id'];
            $sql = "UPDATE roles SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
        }
        
        echo json_encode(['success' => true, 'message' => 'Rol actualizado correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar rol']);
    }
}

function eliminarRol() {
    global $pdo;
    
    try {
        // Obtener datos del DELETE request
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        if (empty($_DELETE['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de rol requerido']);
            return;
        }
        
        // Verificar que no se elimine un rol del sistema
        $stmt = $pdo->prepare("SELECT nombre FROM roles WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        $rol = $stmt->fetch();
        
        if (!$rol) {
            echo json_encode(['success' => false, 'message' => 'Rol no encontrado']);
            return;
        }
        
        // No permitir eliminar roles del sistema
        $rolesSistema = ['ROLE_ADMIN', 'ROLE_SUPERVISOR', 'ROLE_USER'];
        if (in_array($rol['nombre'], $rolesSistema)) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar un rol del sistema']);
            return;
        }
        
        // Verificar si hay usuarios usando este rol
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_roles WHERE rol_id = ?");
        $stmt->execute([$_DELETE['id']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar un rol que está asignado a usuarios']);
            return;
        }
        
        // Eliminar rol
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Rol eliminado correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar rol']);
    }
}
?>
