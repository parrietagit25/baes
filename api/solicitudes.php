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
        } elseif (isset($_GET['gestores'])) {
            obtenerGestores();
        } else {
            obtenerSolicitudes();
        }
        break;
        
    case 'POST':
        // Verificar si es una actualización basada en el parámetro _method
        if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
            actualizarSolicitud();
        } elseif (isset($_POST['action']) && $_POST['action'] === 'aprobar_solicitud') {
            aprobarSolicitud();
        } elseif (isset($_POST['action']) && $_POST['action'] === 'cambiar_gestor') {
            cambiarGestor();
        } elseif (isset($_POST['nuevo_estado']) && isset($_POST['nota_cambio_estado'])) {
            cambiarEstadoSolicitud();
        } else {
            crearSolicitud();
        }
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
                   ub.nombre as banco_nombre, ub.apellido as banco_apellido,
                   b.nombre as banco_institucion,
                   COUNT(DISTINCT n.id) as total_notas
            FROM solicitudes_credito s
            LEFT JOIN usuarios u ON s.gestor_id = u.id
            LEFT JOIN usuarios_banco_solicitudes ubs ON s.id = ubs.solicitud_id AND ubs.estado = 'activo'
            LEFT JOIN usuarios ub ON ubs.usuario_banco_id = ub.id
            LEFT JOIN bancos b ON ub.banco_id = b.id
            LEFT JOIN notas_solicitud n ON s.id = n.solicitud_id
        ";
        
        $whereClause = "";
        $params = [];
        
        if (in_array('ROLE_GESTOR', $userRoles)) {
            $whereClause = " WHERE s.gestor_id = ?";
            $params[] = $usuarioId;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            // Usuarios banco solo ven solicitudes asignadas a ellos
            $whereClause = " WHERE ubs.usuario_banco_id = ?";
            $params[] = $usuarioId;
        } elseif (in_array('ROLE_VENDEDOR', $userRoles)) {
            // Usuarios vendedor solo ven solicitudes asignadas a ellos
            $whereClause = " WHERE s.vendedor_id = ?";
            $params[] = $usuarioId;
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            // Los administradores ven todas las solicitudes
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }
        
        $sql .= $whereClause . " GROUP BY s.id, u.nombre, u.apellido, ub.nombre, ub.apellido, b.nombre ORDER BY s.fecha_creacion DESC";
        
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
                gestor_id, banco_id, tipo_persona, nombre_cliente, cedula, edad, genero,
                direccion, provincia, distrito, corregimiento, barriada, casa_edif,
                numero_casa_apto, telefono, email, email_pipedrive, casado, hijos, perfil_financiero,
                ingreso, tiempo_laborar, profesion, ocupacion, nombre_empresa_negocio, estabilidad_laboral,
                fecha_constitucion, continuidad_laboral, marca_auto, modelo_auto, año_auto, kilometraje,
                precio_especial, abono_porcentaje, abono_monto, comentarios_gestor
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['banco_id'] ?? null,
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
            $_POST['email_pipedrive'] ?? null,
            isset($_POST['casado']) ? 1 : 0,
            $_POST['hijos'] ?? 0,
            $_POST['perfil_financiero'],
            $_POST['ingreso'] ?? null,
            $_POST['tiempo_laborar'] ?? null,
            $_POST['profesion'] ?? null,
            $_POST['ocupacion'] ?? null,
            $_POST['nombre_empresa_negocio'] ?? null,
            $_POST['estabilidad_laboral'] ?? null,
            $_POST['fecha_constitucion'] ?? null,
            $_POST['continuidad_laboral'] ?? null,
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
        
        echo json_encode([
            'success' => true, 
            'message' => 'Solicitud creada correctamente', 
            'data' => ['id' => $solicitudId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear solicitud']);
    }
}

function actualizarSolicitud() {
    global $pdo;
    
    try {
        // Log de depuración
        error_log("=== ACTUALIZAR SOLICITUD DEBUG ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NO SET'));
        error_log("Session user_roles: " . print_r($_SESSION['user_roles'] ?? 'NO SET', true));
        
        if (empty($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
            return;
        }
        
        // Verificar que la solicitud existe
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$_POST['id']]);
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
        } elseif (in_array('ROLE_VENDEDOR', $userRoles) && $solicitud['vendedor_id'] == $_SESSION['user_id']) {
            $puedeEditar = true;
        }
        
        if (!$puedeEditar) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para editar esta solicitud']);
            return;
        }
        
        // Manejar claves foráneas: convertir strings vacíos a NULL
        if (isset($_POST['banco_id']) && $_POST['banco_id'] === '') {
            $_POST['banco_id'] = null;
        }
        if (isset($_POST['vendedor_id']) && $_POST['vendedor_id'] === '') {
            $_POST['vendedor_id'] = null;
        }
        
        // Construir query de actualización
        $campos = [];
        $valores = [];
        
        $camposPermitidos = [
            'banco_id', 'vendedor_id', 'tipo_persona', 'nombre_cliente', 'cedula', 'edad', 'genero',
            'direccion', 'provincia', 'distrito', 'corregimiento', 'barriada',
            'casa_edif', 'numero_casa_apto', 'telefono', 'email', 'email_pipedrive', 'casado',
            'hijos', 'perfil_financiero', 'ingreso', 'tiempo_laborar',
            'profesion', 'ocupacion', 'nombre_empresa_negocio', 'estabilidad_laboral',
            'fecha_constitucion', 'continuidad_laboral',
            'marca_auto', 'modelo_auto', 'año_auto', 'kilometraje',
            'precio_especial', 'abono_porcentaje', 'abono_monto',
            'comentarios_gestor', 'ejecutivo_banco', 'respuesta_banco',
            'letra', 'plazo', 'abono_banco', 'promocion',
            'respuesta_cliente', 'motivo_respuesta', 'fecha_envio_proforma',
            'fecha_firma_cliente', 'fecha_poliza', 'fecha_carta_promesa',
            'comentarios_fi', 'comentarios_ejecutivo_banco', 'estado'
        ];
        
        foreach ($camposPermitidos as $campo) {
            if (isset($_POST[$campo])) {
                $campos[] = "$campo = ?";
                $valores[] = $_POST[$campo];
            }
        }
        
        // Lógica especial para cambio de banco_id
        $bancoIdAnterior = $solicitud['banco_id'];
        $bancoIdNuevo = !empty($_POST['banco_id']) ? $_POST['banco_id'] : null;
        $esAsignacionBanco = false;
        
        error_log("Banco anterior: " . ($bancoIdAnterior ?? 'NULL'));
        error_log("Banco nuevo: " . ($bancoIdNuevo ?? 'NULL'));
        
        // Si se está asignando un banco por primera vez o cambiando de banco
        if ($bancoIdNuevo && $bancoIdAnterior != $bancoIdNuevo) {
            error_log("Asignando banco - cambiando estado a 'En Revisión Banco'");
            $campos[] = "estado = ?";
            $valores[] = 'En Revisión Banco';
            $esAsignacionBanco = true;
        }
        
        if (!empty($campos)) {
            $valores[] = $_POST['id'];
            $sql = "UPDATE solicitudes_credito SET " . implode(', ', $campos) . " WHERE id = ?";
            error_log("SQL: " . $sql);
            error_log("Valores: " . print_r($valores, true));
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
            
            error_log("Update ejecutado exitosamente. Filas afectadas: " . $stmt->rowCount());
            
            // Crear nota según el tipo de actualización
            try {
                if ($esAsignacionBanco) {
                    // Crear nota específica de asignación
                    $stmt = $pdo->prepare("
                        INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
                        VALUES (?, ?, 'Actualización', 'Asignada al Banco', 'Solicitud asignada al banco para revisión')
                    ");
                    $stmt->execute([$_POST['id'], $_SESSION['user_id']]);
                } else {
                    // Crear nota de actualización general
                    $stmt = $pdo->prepare("
                        INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
                        VALUES (?, ?, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada')
                    ");
                    $stmt->execute([$_POST['id'], $_SESSION['user_id']]);
                }
            } catch (PDOException $e) {
                // Si hay error al crear la nota, loguearlo pero no fallar la actualización
                error_log("Error al crear nota: " . $e->getMessage());
                // Continuar con la actualización aunque falle la nota
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Solicitud actualizada correctamente']);
        
    } catch (PDOException $e) {
        error_log("Error en actualizarSolicitud: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar solicitud: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Error general en actualizarSolicitud: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error general: ' . $e->getMessage()]);
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
        
        $solicitudId = $_DELETE['id'];
        
        // Verificar permisos: Solo administradores pueden eliminar solicitudes
        if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden eliminar solicitudes']);
            return;
        }
        
        // Obtener adjuntos para eliminar archivos físicos
        $stmt = $pdo->prepare("SELECT ruta_archivo FROM adjuntos_solicitud WHERE solicitud_id = ?");
        $stmt->execute([$solicitudId]);
        $adjuntos = $stmt->fetchAll();
        
        // Eliminar archivos físicos de adjuntos
        foreach ($adjuntos as $adjunto) {
            $rutaArchivo = '../' . $adjunto['ruta_archivo'];
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
        }
        
        // Eliminar solicitud (las notas y adjuntos se eliminan por CASCADE)
        $stmt = $pdo->prepare("DELETE FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitudId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Solicitud eliminada correctamente junto con sus notas y adjuntos']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        }
        
          } catch (PDOException $e) {
          error_log("Error al eliminar solicitud: " . $e->getMessage());
          http_response_code(500);
          echo json_encode(['success' => false, 'message' => 'Error al eliminar solicitud']);
      }
  }

function obtenerGestores() {
    global $pdo;
    
    try {
        // Verificar que el usuario sea administrador
        if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden ver la lista de gestores']);
            return;
        }
        
        // Obtener usuarios con rol de gestor
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.nombre, u.apellido, u.email, u.activo
            FROM usuarios u
            INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
            INNER JOIN roles r ON ur.rol_id = r.id
            WHERE r.nombre = 'ROLE_GESTOR' AND u.activo = 1
            ORDER BY u.nombre, u.apellido
        ");
        $stmt->execute();
        $gestores = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $gestores]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener gestores']);
    }
}

function cambiarGestor() {
    global $pdo;
    
    try {
        // Verificar que el usuario sea administrador
        if (!in_array('ROLE_ADMIN', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden cambiar el gestor']);
            return;
        }
        
        if (empty($_POST['solicitud_id']) || empty($_POST['nuevo_gestor_id'])) {
            echo json_encode(['success' => false, 'message' => 'Solicitud ID y nuevo gestor son requeridos']);
            return;
        }
        
        $solicitudId = $_POST['solicitud_id'];
        $nuevoGestorId = $_POST['nuevo_gestor_id'];
        
        // Verificar que la solicitud existe
        $stmt = $pdo->prepare("SELECT gestor_id FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        $gestorAnterior = $solicitud['gestor_id'];
        
        // Verificar que el nuevo gestor existe y tiene el rol de gestor
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM usuarios u
            INNER JOIN usuario_roles ur ON u.id = ur.usuario_id
            INNER JOIN roles r ON ur.rol_id = r.id
            WHERE u.id = ? AND r.nombre = 'ROLE_GESTOR' AND u.activo = 1
        ");
        $stmt->execute([$nuevoGestorId]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es un gestor válido']);
            return;
        }
        
        // Actualizar el gestor de la solicitud
        $stmt = $pdo->prepare("UPDATE solicitudes_credito SET gestor_id = ? WHERE id = ?");
        $stmt->execute([$nuevoGestorId, $solicitudId]);
        
        // Crear nota del cambio de gestor
        $stmt = $pdo->prepare("
            SELECT nombre, apellido FROM usuarios WHERE id = ?
        ");
        $stmt->execute([$nuevoGestorId]);
        $nuevoGestor = $stmt->fetch();
        $nombreNuevoGestor = $nuevoGestor['nombre'] . ' ' . $nuevoGestor['apellido'];
        
        $stmt = $pdo->prepare("
            INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
            VALUES (?, ?, 'Actualización', 'Cambio de Gestor', ?)
        ");
        
        $contenidoNota = "Gestor cambiado";
        if ($gestorAnterior) {
            $stmtAnterior = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
            $stmtAnterior->execute([$gestorAnterior]);
            $gestorAnteriorInfo = $stmtAnterior->fetch();
            if ($gestorAnteriorInfo) {
                $nombreGestorAnterior = $gestorAnteriorInfo['nombre'] . ' ' . $gestorAnteriorInfo['apellido'];
                $contenidoNota .= " de " . $nombreGestorAnterior;
            }
        }
        $contenidoNota .= " a " . $nombreNuevoGestor . " por Administrador";
        
        $stmt->execute([$solicitudId, $_SESSION['user_id'], $contenidoNota]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Gestor actualizado correctamente',
            'data' => ['gestor_nombre' => $nombreNuevoGestor]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Error al cambiar gestor: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al cambiar gestor']);
    }
}
  
function aprobarSolicitud() {
    global $pdo;
    
    try {
        // Verificar que el usuario sea banco
        if (!in_array('ROLE_BANCO', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los usuarios banco pueden aprobar solicitudes']);
            return;
        }
        
        // Validar datos requeridos
        if (empty($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
            return;
        }
        
        if (empty($_POST['accion'])) {
            echo json_encode(['success' => false, 'message' => 'Acción requerida (aprobar/rechazar)']);
            return;
        }
        
        $solicitudId = $_POST['id'];
        $accion = $_POST['accion']; // 'aprobar' o 'rechazar'
        $usuarioId = $_SESSION['user_id'];
        
        // Verificar que la solicitud existe y está asignada al usuario banco
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_credito WHERE id = ? AND banco_id = ?");
        $stmt->execute([$solicitudId, $usuarioId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o no asignada a usted']);
            return;
        }
        
        // Verificar que la solicitud esté en estado correcto
        if ($solicitud['estado'] !== 'En Revisión Banco') {
            echo json_encode(['success' => false, 'message' => 'La solicitud no está en estado de revisión']);
            return;
        }
        
        // Preparar datos según la acción
        if ($accion === 'aprobar') {
            $respuestaBanco = 'Aprobado';
            $nuevoEstado = 'Aprobada';
            $tituloNota = 'Solicitud Aprobada';
            $contenidoNota = 'La solicitud ha sido aprobada por el banco';
            
            // Validar campos requeridos para aprobación
            if (empty($_POST['letra']) || empty($_POST['plazo'])) {
                echo json_encode(['success' => false, 'message' => 'Letra y plazo son requeridos para aprobar']);
                return;
            }
            
        } elseif ($accion === 'rechazar') {
            $respuestaBanco = 'Rechazado';
            $nuevoEstado = 'Rechazada';
            $tituloNota = 'Solicitud Rechazada';
            $contenidoNota = 'La solicitud ha sido rechazada por el banco';
            
            if (empty($_POST['comentarios_ejecutivo_banco'])) {
                echo json_encode(['success' => false, 'message' => 'Comentarios son requeridos para rechazar']);
                return;
            }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            return;
        }
        
        // Actualizar la solicitud
        $stmt = $pdo->prepare("
            UPDATE solicitudes_credito 
            SET estado = ?, respuesta_banco = ?, 
                ejecutivo_banco = ?, letra = ?, plazo = ?, abono_banco = ?, 
                promocion = ?, comentarios_ejecutivo_banco = ?,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nuevoEstado,
            $respuestaBanco,
            $_POST['ejecutivo_banco'] ?? null,
            $_POST['letra'] ?? null,
            $_POST['plazo'] ?? null,
            $_POST['abono_banco'] ?? null,
            $_POST['promocion'] ?? null,
            $_POST['comentarios_ejecutivo_banco'] ?? null,
            $solicitudId
        ]);
        
        // Crear nota de la decisión
        $stmt = $pdo->prepare("
            INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
            VALUES (?, ?, 'Respuesta Banco', ?, ?)
        ");
        $stmt->execute([$solicitudId, $usuarioId, $tituloNota, $contenidoNota]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Solicitud ' . ($accion === 'aprobar' ? 'aprobada' : 'rechazada') . ' correctamente',
            'data' => [
                'estado' => $nuevoEstado,
                'respuesta_banco' => $respuestaBanco
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Error al aprobar/rechazar solicitud: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
    }
}

function cambiarEstadoSolicitud() {
    global $pdo;
    
    try {
        // Verificar permisos: Admin puede cambiar cualquier estado, Vendedor solo de solicitudes asignadas
        $userRoles = $_SESSION['user_roles'];
        $puedeCambiarEstado = false;
        
        if (in_array('ROLE_ADMIN', $userRoles)) {
            $puedeCambiarEstado = true;
        } elseif (in_array('ROLE_VENDEDOR', $userRoles)) {
            // Verificar que el vendedor tenga la solicitud asignada
            $stmt = $pdo->prepare("SELECT vendedor_id FROM solicitudes_credito WHERE id = ?");
            $stmt->execute([$_POST['solicitud_id']]);
            $solicitud = $stmt->fetch();
            
            if ($solicitud && $solicitud['vendedor_id'] == $_SESSION['user_id']) {
                $puedeCambiarEstado = true;
            }
        }
        
        if (!$puedeCambiarEstado) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para cambiar el estado de esta solicitud']);
            return;
        }
        
        // Validar datos requeridos
        $solicitud_id = $_POST['solicitud_id'] ?? null;
        $nuevo_estado = $_POST['nuevo_estado'] ?? null;
        $nota = $_POST['nota_cambio_estado'] ?? null;
        
        if (!$solicitud_id || !$nuevo_estado || !$nota) {
            echo json_encode(['success' => false, 'message' => 'Datos requeridos faltantes']);
            return;
        }
        
        // Validar que el estado sea válido
        $estados_validos = ['Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
        if (!in_array($nuevo_estado, $estados_validos)) {
            echo json_encode(['success' => false, 'message' => 'Estado no válido']);
            return;
        }
        
        // Verificar que la solicitud existe
        $stmt = $pdo->prepare("SELECT id, estado, nombre_cliente FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitud_id]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        $estado_anterior = $solicitud['estado'];
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        try {
            // Actualizar estado de la solicitud
            $stmt = $pdo->prepare("
                UPDATE solicitudes_credito 
                SET estado = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nuevo_estado, $solicitud_id]);
            
            // Crear nota en el muro
            $rol_usuario = in_array('ROLE_ADMIN', $userRoles) ? 'administrador' : 'vendedor';
            $titulo_nota = "Estado cambiado por {$rol_usuario}";
            $contenido_nota = "Estado cambiado de '{$estado_anterior}' a '{$nuevo_estado}'. Motivo: {$nota}";
            
            $stmt = $pdo->prepare("
                INSERT INTO notas_solicitud (solicitud_id, usuario_id, tipo_nota, titulo, contenido)
                VALUES (?, ?, 'Actualización', ?, ?)
            ");
            $stmt->execute([$solicitud_id, $_SESSION['user_id'], $titulo_nota, $contenido_nota]);
            
            // Si el estado es Desistimiento, también actualizar respuesta_cliente
            if ($nuevo_estado === 'Desistimiento') {
                $stmt = $pdo->prepare("
                    UPDATE solicitudes_credito 
                    SET respuesta_cliente = 'Rechaza', motivo_respuesta = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nota, $solicitud_id]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Estado de la solicitud de {$solicitud['nombre_cliente']} cambiado exitosamente de '{$estado_anterior}' a '{$nuevo_estado}'"
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de solicitud: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al cambiar el estado de la solicitud']);
    }
}
?>
