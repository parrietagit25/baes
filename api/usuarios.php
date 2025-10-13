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
            obtenerUsuario($_GET['id']);
        } elseif (isset($_GET['validar_email'])) {
            validarEmail($_GET['validar_email'], $_GET['excluir_id'] ?? null);
        } elseif (isset($_GET['bancos'])) {
            obtenerBancos();
        } else {
            obtenerUsuarios();
        }
        break;
        
    case 'POST':
        crearUsuario();
        break;
        
    case 'PUT':
        actualizarUsuario();
        break;
        
    case 'DELETE':
        eliminarUsuario();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerUsuario($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, GROUP_CONCAT(ur.rol_id) as rol_ids
            FROM usuarios u
            LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Convertir rol_ids a array de objetos
            $rolIds = $usuario['rol_ids'] ? explode(',', $usuario['rol_ids']) : [];
            $usuario['roles'] = array_map(function($rolId) {
                return ['rol_id' => (int)$rolId];
            }, $rolIds);
            
            unset($usuario['rol_ids']);
            unset($usuario['password']); // No enviar contraseña
            
            echo json_encode(['success' => true, 'data' => $usuario]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerUsuarios() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT u.*, GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles, b.nombre as banco_nombre
            FROM usuarios u
            LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
            LEFT JOIN roles r ON ur.rol_id = r.id
            LEFT JOIN bancos b ON u.banco_id = b.id
            GROUP BY u.id
            ORDER BY u.fecha_creacion DESC
        ");
        $usuarios = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $usuarios]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function validarEmail($email, $excluirId = null) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = ?";
        $params = [$email];
        
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

function crearUsuario() {
    global $pdo;
    
    try {
        // Validar datos requeridos
        $camposRequeridos = ['nombre', 'apellido', 'email', 'password'];
        foreach ($camposRequeridos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => "El campo $campo es requerido"]);
                return;
            }
        }
        
        // Validar email único
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
            return;
        }
        
        // Hash de la contraseña
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Insertar usuario
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, apellido, email, password, pais, cargo, telefono, banco_id,
                                id_cobrador, id_vendedor, activo, primer_acceso)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['nombre'],
            $_POST['apellido'],
            $_POST['email'],
            $passwordHash,
            $_POST['pais'] ?? null,
            $_POST['cargo'] ?? null,
            $_POST['telefono'] ?? null,
            (!empty($_POST['banco_id'])) ? $_POST['banco_id'] : null,
            (!empty($_POST['id_cobrador'])) ? $_POST['id_cobrador'] : null,
            (!empty($_POST['id_vendedor'])) ? $_POST['id_vendedor'] : null,
            isset($_POST['activo']) ? 1 : 0,
            isset($_POST['primer_acceso']) ? 1 : 0
        ]);
        
        $usuarioId = $pdo->lastInsertId();
        
        // Asignar roles
        if (isset($_POST['roles']) && is_array($_POST['roles'])) {
            $stmt = $pdo->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)");
            foreach ($_POST['roles'] as $rolId) {
                $stmt->execute([$usuarioId, $rolId]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente', 'id' => $usuarioId]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear usuario']);
    }
}

function actualizarUsuario() {
    global $pdo;
    
    try {
        // Obtener datos del PUT request
        $input = file_get_contents("php://input");
        parse_str($input, $_PUT);
        
        // Manejar arrays que no se parsean correctamente con parse_str
        if (strpos($input, 'roles%5B%5D') !== false || strpos($input, 'roles[]') !== false) {
            // Extraer roles manualmente
            $roles = [];
            preg_match_all('/roles(?:%5B%5D|\[\])=(\d+)/', $input, $matches);
            if (!empty($matches[1])) {
                $roles = $matches[1];
            }
            $_PUT['roles'] = $roles;
        }
        
        if (empty($_PUT['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
            return;
        }
        
        // Verificar si el usuario existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
        $stmt->execute([$_PUT['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            return;
        }
        
        // Validar email único (excluyendo el usuario actual)
        if (!empty($_PUT['email'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$_PUT['email'], $_PUT['id']]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
                return;
            }
        }
        
        // Construir query de actualización
        $campos = [];
        $valores = [];
        
        $camposPermitidos = ['nombre', 'apellido', 'email', 'pais', 'cargo', 'telefono', 'banco_id',
                             'id_cobrador', 'id_vendedor', 'activo', 'primer_acceso'];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($_PUT[$campo])) {
                $campos[] = "$campo = ?";
                // Convertir strings vacíos a NULL para campos de clave foránea
                $valor = $_PUT[$campo];
                if (($campo === 'banco_id' || $campo === 'id_cobrador' || $campo === 'id_vendedor') && $valor === '') {
                    $valor = null;
                }
                $valores[] = $valor;
            }
        }
        
        // Manejar checkboxes
        if (isset($_PUT['activo'])) {
            $valores[array_search('activo = ?', $campos)] = $_PUT['activo'] ? 1 : 0;
        }
        if (isset($_PUT['primer_acceso'])) {
            $valores[array_search('primer_acceso = ?', $campos)] = $_PUT['primer_acceso'] ? 1 : 0;
        }
        
        // Actualizar contraseña si se proporciona
        if (!empty($_PUT['password'])) {
            $campos[] = "password = ?";
            $valores[] = password_hash($_PUT['password'], PASSWORD_DEFAULT);
        }
        
        if (!empty($campos)) {
            $valores[] = $_PUT['id'];
            $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
        }
        
        // Actualizar roles
        if (isset($_PUT['roles']) && is_array($_PUT['roles'])) {
            // Eliminar roles actuales
            $stmt = $pdo->prepare("DELETE FROM usuario_roles WHERE usuario_id = ?");
            $stmt->execute([$_PUT['id']]);
            
            // Insertar nuevos roles
            $stmt = $pdo->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)");
            foreach ($_PUT['roles'] as $rolId) {
                $stmt->execute([$_PUT['id'], $rolId]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Error en actualizarUsuario: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . $e->getMessage()]);
    }
}

function eliminarUsuario() {
    global $pdo;
    
    try {
        // Obtener datos del DELETE request
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        if (empty($_DELETE['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
            return;
        }
        
        // Verificar que no se elimine el usuario administrador principal
        if ($_DELETE['id'] == 1) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar el usuario administrador principal']);
            return;
        }
        
        // Eliminar usuario (los roles se eliminan por CASCADE)
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario']);
    }
}

function obtenerBancos() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, nombre, codigo FROM bancos WHERE activo = 1 ORDER BY nombre ASC");
        $bancos = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $bancos]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}
?>
