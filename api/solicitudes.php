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
        if (isset($_GET['id'])) {
            obtenerSolicitud($_GET['id']);
        } else {
            obtenerSolicitudes();
        }
        break;
        
    case 'POST':
        crearSolicitud();
        break;
        
    case 'PUT':
        actualizarSolicitud();
        break;
        
    case 'DELETE':
        eliminarSolicitud();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerSolicitudes() {
    global $pdo;
    
    try {
        $usuarioId = $_SESSION['user_id'];
        $userRoles = $_SESSION['user_roles'];
        
        // Construir query según el rol del usuario
        $sql = "
            SELECT s.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido,
                   COUNT(n.id) as total_notas
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.gestor_id = u.id
            LEFT JOIN notas_solicitud n ON s.id = n.solicitud_id
        ";
        
        $whereClause = "";
        $params = [];
        
        if (in_array('ROLE_GESTOR', $userRoles)) {
            $whereClause = " WHERE s.gestor_id = ?";
            $params[] = $usuarioId;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            $whereClause = " WHERE s.respuesta_banco IN ('Pendiente', 'Pre Aprobado')";
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            // Los administradores ven todas las solicitudes
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }
        
        $sql .= $whereClause . " GROUP BY s.id ORDER BY s.fecha_creacion DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $solicitudes = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $solicitudes]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function obtenerSolicitud($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.nombre as gestor_nombre, u.apellido as gestor_apellido
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.gestor_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $solicitud = $stmt->fetch();
        
        if ($solicitud) {
            // Obtener notas de la solicitud
            $stmt = $pdo->prepare("
                SELECT n.*, u.nombre, u.apellido
                FROM notas_solicitud n
                LEFT JOIN usuarios u ON n.usuario_id = u.id
                WHERE n.solicitud_id = ?
                ORDER BY n.fecha_creacion DESC
            ");
            $stmt->execute([$id]);
            $solicitud['notas'] = $stmt->fetchAll();
            
            // Obtener documentos
            $stmt = $pdo->prepare("
                SELECT d.*, u.nombre, u.apellido
                FROM documentos_solicitud d
                LEFT JOIN usuarios u ON d.usuario_id = u.id
                WHERE d.solicitud_id = ?
                ORDER BY d.fecha_subida DESC
            ");
            $stmt->execute([$id]);
            $solicitud['documentos'] = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $solicitud]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function crearSolicitud() {
    global $pdo;
    
    try {
        // Verificar que el usuario sea gestor o admin
        if (!in_array('ROLE_GESTOR', $_SESSION['user_roles']) && !in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los gestores y administradores pueden crear solicitudes']);
            return;
        }
        
        // Validar campos requeridos
        $camposRequeridos = ['tipo_persona', 'nombre_cliente', 'cedula', 'perfil_financiero'];
        foreach ($camposRequeridos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => "El campo $campo es requerido"]);
                return;
            }
        }
        
        // Insertar solicitud
        $stmt = $pdo->prepare("
            INSERT INTO solicitudes_credito (
                gestor_id, tipo_persona, nombre_cliente, cedula, edad, genero,
                direccion, provincia, distrito, corregimiento, barriada, casa_edif,
                numero_casa_apto, telefono, email, casado, hijos, perfil_financiero,
                ingreso, tiempo_laborar, nombre_empresa_negocio, estabilidad_laboral,
                fecha_constitucion, marca_auto, modelo_auto, año_auto, kilometraje,
                precio_especial, abono_porcentaje, abono_monto, comentarios_gestor
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['tipo_persona'],
            $_POST['nombre_cliente'],
            $_POST['cedula'],
            $_POST['edad'] ?? null,
            $_POST['genero'] ?? null,
            $_POST['direccion'] ?? null,
            $_POST['provincia'] ?? null,
            $_POST['distrito'] ?? null,
            $_POST['corregimiento'] ?? null,
            $_POST['barriada'] ?? null,
            $_POST['casa_edif'] ?? null,
            $_POST['numero_casa_apto'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['email'] ?? null,
            isset($_POST['casado']) ? 1 : 0,
            $_POST['hijos'] ?? 0,
            $_POST['perfil_financiero'],
            $_POST['ingreso'] ?? null,
            $_POST['tiempo_laborar'] ?? null,
            $_POST['nombre_empresa_negocio'] ?? null,
            $_POST['estabilidad_laboral'] ?? null,
            $_POST['fecha_constitucion'] ?? null,
            $_POST['marca_auto'] ?? null,
            $_POST['modelo_auto'] ?? null,
            $_POST['año_auto'] ?? null,
            $_POST['kilometraje'] ?? null,
            $_POST['precio_especial'] ?? null,
            $_POST['abono_porcentaje'] ?? null,
            $_POST['abono_monto'] ?? null,
            $_POST['comentarios_gestor'] ?? null
        ]);
        
        $solicitudId = $pdo->lastInsertId();
        
        // Crear nota inicial
        $stmt = $pdo->prepare("
            INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
            VALUES (?, ?, 'Comentario', 'Solicitud Creada', 'Solicitud de crédito creada por el gestor')
        ");
        $stmt->execute([$solicitudId, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Solicitud creada correctamente', 'id' => $solicitudId]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear solicitud']);
    }
}

function actualizarSolicitud() {
    global $pdo;
    
    try {
        // Obtener datos del PUT request
        parse_str(file_get_contents("php://input"), $_PUT);
        
        if (empty($_PUT['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
            return;
        }
        
        // Verificar que la solicitud existe
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$_PUT['id']]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        // Verificar permisos según el rol
        $userRoles = $_SESSION['user_roles'];
        $puedeEditar = false;
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $puedeEditar = true;
        } elseif (in_array('ROLE_GESTOR', $userRoles) && $solicitud['gestor_id'] == $_SESSION['user_id']) {
            $puedeEditar = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            $puedeEditar = true;
        }
        
        if (!$puedeEditar) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para editar esta solicitud']);
            return;
        }
        
        // Construir query de actualización
        $campos = [];
        $valores = [];
        
        $camposPermitidos = [
            'tipo_persona', 'nombre_cliente', 'cedula', 'edad', 'genero',
            'direccion', 'provincia', 'distrito', 'corregimiento', 'barriada',
            'casa_edif', 'numero_casa_apto', 'telefono', 'email', 'casado',
            'hijos', 'perfil_financiero', 'ingreso', 'tiempo_laborar',
            'nombre_empresa_negocio', 'estabilidad_laboral', 'fecha_constitucion',
            'marca_auto', 'modelo_auto', 'año_auto', 'kilometraje',
            'precio_especial', 'abono_porcentaje', 'abono_monto',
            'comentarios_gestor', 'ejecutivo_banco', 'respuesta_banco',
            'letra', 'plazo', 'abono_banco', 'promocion',
            'respuesta_cliente', 'motivo_respuesta', 'fecha_envio_proforma',
            'fecha_firma_cliente', 'fecha_poliza', 'fecha_carta_promesa',
            'comentarios_fi', 'comentarios_ejecutivo_banco', 'estado'
        ];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($_PUT[$campo])) {
                $campos[] = "$campo = ?";
                $valores[] = $_PUT[$campo];
            }
        }
        
        if (!empty($campos)) {
            $valores[] = $_PUT['id'];
            $sql = "UPDATE solicitudes_credito SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
            
            // Crear nota de actualización
            $stmt = $pdo->prepare("
                INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
                VALUES (?, ?, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada')
            ");
            $stmt->execute([$_PUT['id'], $_SESSION['user_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Solicitud actualizada correctamente']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar solicitud']);
    }
}

function eliminarSolicitud() {
    global $pdo;
    
    try {
        // Obtener datos del DELETE request
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        if (empty($_DELETE['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
            return;
        }
        
        // Solo administradores pueden eliminar
        if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo administradores pueden eliminar solicitudes']);
            return;
        }
        
        // Eliminar solicitud (las notas y documentos se eliminan por CASCADE)
        $stmt = $pdo->prepare("DELETE FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$_DELETE['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Solicitud eliminada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar solicitud']);
    }
}
?>
