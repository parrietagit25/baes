<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que el usuario tenga permisos para gestionar bancos (solo admin)
if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden gestionar bancos']);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            obtenerBanco($_GET['id']);
        } elseif (isset($_GET['usuarios'])) {
            obtenerUsuariosBanco($_GET['usuarios']);
        } else {
            obtenerBancos();
        }
        break;
        
    case 'POST':
        crearBanco();
        break;
        
    case 'PUT':
        actualizarBanco();
        break;
        
    case 'DELETE':
        eliminarBanco();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerBancos() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT b.*, 
                   COUNT(u.id) as usuarios_count
            FROM bancos b
            LEFT JOIN usuarios u ON b.id = u.banco_id
            GROUP BY b.id
            ORDER BY b.nombre ASC
        ");
        $bancos = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $bancos]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerBanco($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM bancos WHERE id = ?");
        $stmt->execute([$id]);
        $banco = $stmt->fetch();
        
        if ($banco) {
            echo json_encode(['success' => true, 'data' => $banco]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Banco no encontrado']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function crearBanco() {
    global $pdo;
    
    try {
        // Validar campos requeridos
        $camposRequeridos = ['nombre', 'codigo'];
        foreach ($camposRequeridos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => "El campo $campo es requerido"]);
                return;
            }
        }
        
        // Verificar que el código no exista
        $stmt = $pdo->prepare("SELECT id FROM bancos WHERE codigo = ?");
        $stmt->execute([$_POST['codigo']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un banco con este código']);
            return;
        }
        
        // Insertar banco
        $stmt = $pdo->prepare("
            INSERT INTO bancos (
                nombre, codigo, descripcion, direccion, telefono, email, sitio_web,
                contacto_principal, telefono_contacto, email_contacto, activo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['nombre'],
            $_POST['codigo'],
            $_POST['descripcion'] ?? null,
            $_POST['direccion'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['email'] ?? null,
            $_POST['sitio_web'] ?? null,
            $_POST['contacto_principal'] ?? null,
            $_POST['telefono_contacto'] ?? null,
            $_POST['email_contacto'] ?? null,
            isset($_POST['activo']) ? 1 : 1
        ]);
        
        $bancoId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Banco creado correctamente',
            'data' => ['id' => $bancoId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear banco']);
    }
}

function actualizarBanco() {
    global $pdo;
    
    try {
        // Obtener datos del PUT request
        parse_str(file_get_contents("php://input"), $_PUT);
        
        if (empty($_PUT['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de banco requerido']);
            return;
        }
        
        // Verificar que el banco existe
        $stmt = $pdo->prepare("SELECT id FROM bancos WHERE id = ?");
        $stmt->execute([$_PUT['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Banco no encontrado']);
            return;
        }
        
        // Verificar que el código no esté en uso por otro banco
        if (!empty($_PUT['codigo'])) {
            $stmt = $pdo->prepare("SELECT id FROM bancos WHERE codigo = ? AND id != ?");
            $stmt->execute([$_PUT['codigo'], $_PUT['id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro banco con este código']);
                return;
            }
        }
        
        // Actualizar banco
        $stmt = $pdo->prepare("
            UPDATE bancos SET 
                nombre = ?, codigo = ?, descripcion = ?, direccion = ?, 
                telefono = ?, email = ?, sitio_web = ?, contacto_principal = ?, 
                telefono_contacto = ?, email_contacto = ?, activo = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_PUT['nombre'],
            $_PUT['codigo'],
            $_PUT['descripcion'] ?? null,
            $_PUT['direccion'] ?? null,
            $_PUT['telefono'] ?? null,
            $_PUT['email'] ?? null,
            $_PUT['sitio_web'] ?? null,
            $_PUT['contacto_principal'] ?? null,
            $_PUT['telefono_contacto'] ?? null,
            $_PUT['email_contacto'] ?? null,
            isset($_PUT['activo']) ? 1 : 0,
            $_PUT['id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Banco actualizado correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar banco']);
    }
}

function eliminarBanco() {
    global $pdo;
    
    try {
        // Obtener datos del DELETE request
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        if (empty($_DELETE['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de banco requerido']);
            return;
        }
        
        // Verificar que el banco existe
        $stmt = $pdo->prepare("SELECT id FROM bancos WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Banco no encontrado']);
            return;
        }
        
        // Verificar si hay solicitudes asociadas a este banco
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM solicitudes_credito WHERE ejecutivo_banco LIKE ?");
        $stmt->execute(['%' . $_DELETE['id'] . '%']);
        $solicitudes = $stmt->fetch()['total'];
        
        if ($solicitudes > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "No se puede eliminar el banco porque tiene $solicitudes solicitudes asociadas"
            ]);
            return;
        }
        
        // Eliminar banco
        $stmt = $pdo->prepare("DELETE FROM bancos WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Banco eliminado correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar banco']);
    }
}

function obtenerUsuariosBanco($bancoId) {
    global $pdo;
    
    try {
        // Verificar que el banco existe
        $stmt = $pdo->prepare("SELECT id, nombre FROM bancos WHERE id = ?");
        $stmt->execute([$bancoId]);
        $banco = $stmt->fetch();
        
        if (!$banco) {
            echo json_encode(['success' => false, 'message' => 'Banco no encontrado']);
            return;
        }
        
        // Obtener usuarios asignados al banco con sus roles
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.apellido, u.email, u.activo, u.fecha_creacion,
                   GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles
            FROM usuarios u
            LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
            LEFT JOIN roles r ON ur.rol_id = r.id
            WHERE u.banco_id = ?
            GROUP BY u.id
            ORDER BY u.nombre ASC, u.apellido ASC
        ");
        $stmt->execute([$bancoId]);
        $usuarios = $stmt->fetchAll();
        
        // Procesar roles para cada usuario
        foreach ($usuarios as &$usuario) {
            $usuario['roles'] = $usuario['roles'] ? explode(', ', $usuario['roles']) : [];
        }
        
        echo json_encode(['success' => true, 'data' => $usuarios]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios del banco']);
    }
}
?>
