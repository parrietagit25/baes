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
            obtenerCitas($_GET['solicitud_id']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
        }
        break;
        
    case 'POST':
        crearCita();
        break;
        
    case 'PUT':
        actualizarAsistencia();
        break;
        
    case 'DELETE':
        eliminarCita();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

function obtenerCitas($solicitudId) {
    global $pdo;
    
    try {
        // Verificar que la solicitud existe y tiene una evaluación seleccionada
        $stmt = $pdo->prepare("
            SELECT s.id, s.evaluacion_seleccionada 
            FROM solicitudes_credito s 
            WHERE s.id = ? AND s.evaluacion_seleccionada IS NOT NULL
        ");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o no tiene propuesta seleccionada']);
            return;
        }
        
        // Verificar permisos
        $userRoles = $_SESSION['user_roles'];
        $tieneAcceso = false;
        
        if (in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_GESTOR', $userRoles)) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            // Verificar si el usuario banco es el dueño de la evaluación seleccionada
            $stmt = $pdo->prepare("
                SELECT e.usuario_banco_id 
                FROM evaluaciones_banco e
                INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
                WHERE e.id = ? AND ubs.usuario_banco_id = ?
            ");
            $stmt->execute([$solicitud['evaluacion_seleccionada'], $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $tieneAcceso = true;
            }
        }
        
        if (!$tieneAcceso) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene acceso a esta solicitud']);
            return;
        }
        
        // Obtener citas
        $stmt = $pdo->prepare("
            SELECT * 
            FROM citas_firma 
            WHERE solicitud_id = ? 
            ORDER BY fecha_cita DESC, hora_cita DESC
        ");
        $stmt->execute([$solicitudId]);
        $citas = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $citas]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
}

function crearCita() {
    global $pdo;
    
    try {
        // Validar campos requeridos
        if (empty($_POST['solicitud_id']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita'])) {
            echo json_encode(['success' => false, 'message' => 'Solicitud ID, fecha y hora son requeridos']);
            return;
        }
        
        $solicitudId = $_POST['solicitud_id'];
        
        // Verificar que la solicitud existe y tiene una evaluación seleccionada
        $stmt = $pdo->prepare("
            SELECT s.id, s.evaluacion_seleccionada 
            FROM solicitudes_credito s 
            WHERE s.id = ? AND s.evaluacion_seleccionada IS NOT NULL
        ");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada o no tiene propuesta seleccionada']);
            return;
        }
        
        // Verificar permisos
        $userRoles = $_SESSION['user_roles'];
        $tieneAcceso = false;
        
        if (in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_GESTOR', $userRoles)) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            // Verificar si el usuario banco es el dueño de la evaluación seleccionada
            $stmt = $pdo->prepare("
                SELECT e.usuario_banco_id 
                FROM evaluaciones_banco e
                INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
                WHERE e.id = ? AND ubs.usuario_banco_id = ?
            ");
            $stmt->execute([$solicitud['evaluacion_seleccionada'], $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $tieneAcceso = true;
            }
        }
        
        if (!$tieneAcceso) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para crear citas en esta solicitud']);
            return;
        }
        
        // Insertar cita
        $stmt = $pdo->prepare("
            INSERT INTO citas_firma (solicitud_id, fecha_cita, hora_cita, comentarios)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $solicitudId,
            $_POST['fecha_cita'],
            $_POST['hora_cita'],
            $_POST['comentarios'] ?? null
        ]);
        
        $citaId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cita creada correctamente', 
            'data' => ['id' => $citaId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear cita: ' . $e->getMessage()]);
    }
}

function actualizarAsistencia() {
    global $pdo;
    
    try {
        // Obtener datos del PUT request
        parse_str(file_get_contents("php://input"), $_PUT);
        
        if (empty($_PUT['id']) || empty($_PUT['asistio'])) {
            echo json_encode(['success' => false, 'message' => 'ID de cita y asistencia son requeridos']);
            return;
        }
        
        $citaId = $_PUT['id'];
        $asistio = $_PUT['asistio'];
        
        // Validar valor de asistio
        if (!in_array($asistio, ['asistio', 'no_asistio', 'pendiente'])) {
            echo json_encode(['success' => false, 'message' => 'Valor de asistencia inválido']);
            return;
        }
        
        // Verificar que la cita existe y obtener información de la solicitud
        $stmt = $pdo->prepare("
            SELECT c.*, s.evaluacion_seleccionada 
            FROM citas_firma c
            INNER JOIN solicitudes_credito s ON c.solicitud_id = s.id
            WHERE c.id = ?
        ");
        $stmt->execute([$citaId]);
        $cita = $stmt->fetch();
        
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
            return;
        }
        
        // Verificar permisos
        $userRoles = $_SESSION['user_roles'];
        $tieneAcceso = false;
        
        if (in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_GESTOR', $userRoles)) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            // Verificar si el usuario banco es el dueño de la evaluación seleccionada
            if ($cita['evaluacion_seleccionada']) {
                $stmt = $pdo->prepare("
                    SELECT e.usuario_banco_id 
                    FROM evaluaciones_banco e
                    INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
                    WHERE e.id = ? AND ubs.usuario_banco_id = ?
                ");
                $stmt->execute([$cita['evaluacion_seleccionada'], $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $tieneAcceso = true;
                }
            }
        }
        
        if (!$tieneAcceso) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para actualizar esta cita']);
            return;
        }
        
        // Actualizar asistencia
        $stmt = $pdo->prepare("
            UPDATE citas_firma 
            SET asistio = ? 
            WHERE id = ?
        ");
        $stmt->execute([$asistio, $citaId]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Asistencia actualizada correctamente'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar asistencia: ' . $e->getMessage()]);
    }
}

function eliminarCita() {
    global $pdo;
    
    try {
        // Obtener datos del DELETE request
        $input = file_get_contents("php://input");
        parse_str($input, $_DELETE);
        $citaId = $_DELETE['id'] ?? $_GET['id'] ?? null;
        
        if (empty($citaId)) {
            echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
            return;
        }
        
        // Convertir a entero para seguridad
        $citaId = (int)$citaId;
        
        // Primero, verificar que la cita existe SOLO en citas_firma
        // NO hacer JOIN con otras tablas para evitar efectos secundarios
        $stmt = $pdo->prepare("SELECT id, solicitud_id FROM citas_firma WHERE id = ?");
        $stmt->execute([$citaId]);
        $cita = $stmt->fetch();
        
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
            return;
        }
        
        $solicitudId = $cita['solicitud_id'];
        
        // Verificar permisos - obtener información de la solicitud por separado
        $stmt = $pdo->prepare("SELECT evaluacion_seleccionada FROM solicitudes_credito WHERE id = ?");
        $stmt->execute([$solicitudId]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            return;
        }
        
        // VERIFICACIÓN CRÍTICA: Guardar el estado de las evaluaciones ANTES de eliminar
        // para detectar si algo se elimina incorrectamente
        $evaluacionSeleccionada = $solicitud['evaluacion_seleccionada'];
        $evaluacionesAntes = [];
        if ($evaluacionSeleccionada) {
            $stmt = $pdo->prepare("SELECT id, solicitud_id, usuario_banco_id FROM evaluaciones_banco WHERE id = ?");
            $stmt->execute([$evaluacionSeleccionada]);
            $eval = $stmt->fetch();
            if ($eval) {
                $evaluacionesAntes[$eval['id']] = $eval;
            }
        }
        
        // También verificar todas las evaluaciones de la solicitud
        $stmt = $pdo->prepare("SELECT id FROM evaluaciones_banco WHERE solicitud_id = ?");
        $stmt->execute([$solicitudId]);
        $todasEvaluaciones = $stmt->fetchAll();
        $totalEvaluacionesAntes = count($todasEvaluaciones);
        
        $userRoles = $_SESSION['user_roles'];
        $tieneAcceso = false;
        
        if (in_array('ROLE_ADMIN', $userRoles) || in_array('ROLE_GESTOR', $userRoles)) {
            $tieneAcceso = true;
        } elseif (in_array('ROLE_BANCO', $userRoles)) {
            // Verificar si el usuario banco es el dueño de la evaluación seleccionada
            if ($solicitud['evaluacion_seleccionada']) {
                $stmt = $pdo->prepare("
                    SELECT e.usuario_banco_id 
                    FROM evaluaciones_banco e
                    INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
                    WHERE e.id = ? AND ubs.usuario_banco_id = ?
                ");
                $stmt->execute([$solicitud['evaluacion_seleccionada'], $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $tieneAcceso = true;
                }
            }
        }
        
        if (!$tieneAcceso) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar esta cita']);
            return;
        }
        
        // CRÍTICO: Iniciar transacción DESPUÉS de todas las validaciones
        // para asegurar que solo se elimine de citas_firma
        $pdo->beginTransaction();
        
        // Verificar nuevamente que la cita existe antes de eliminar
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM citas_firma WHERE id = ?");
        $stmt->execute([$citaId]);
        $verificacion = $stmt->fetch();
        
        if ($verificacion['count'] == 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'La cita ya no existe']);
            return;
        }
        
        // IMPORTANTE: Solo eliminar de citas_firma
        // NO tocar evaluaciones_banco ni ninguna otra tabla
        // Usar una consulta muy específica para evitar efectos secundarios
        $stmt = $pdo->prepare("DELETE FROM citas_firma WHERE id = ? AND solicitud_id = ?");
        $resultado = $stmt->execute([$citaId, $solicitudId]);
        $filasAfectadas = $stmt->rowCount();
        
        if (!$resultado || $filasAfectadas === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'No se pudo eliminar la cita']);
            return;
        }
        
        // Verificar que solo se eliminó 1 fila
        if ($filasAfectadas !== 1) {
            $pdo->rollBack();
            error_log("ERROR: Se intentó eliminar más de una cita. Filas afectadas: " . $filasAfectadas);
            echo json_encode(['success' => false, 'message' => 'Error: se intentó eliminar más de un registro']);
            return;
        }
        
        // VERIFICACIÓN POST-ELIMINACIÓN: Verificar que las evaluaciones siguen existiendo
        if ($evaluacionSeleccionada) {
            $stmt = $pdo->prepare("SELECT id FROM evaluaciones_banco WHERE id = ?");
            $stmt->execute([$evaluacionSeleccionada]);
            $evalDespues = $stmt->fetch();
            
            if (!$evalDespues) {
                // Si la evaluación desapareció, hacer rollback
                $pdo->rollBack();
                error_log("ERROR CRÍTICO: Se eliminó una evaluación del banco al eliminar la cita. Evaluación ID: " . $evaluacionSeleccionada);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error: No se puede eliminar la cita porque afectaría otras tablas. Contacte al administrador.'
                ]);
                return;
            }
        }
        
        // Verificar que el total de evaluaciones no cambió
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM evaluaciones_banco WHERE solicitud_id = ?");
        $stmt->execute([$solicitudId]);
        $totalEvaluacionesDespues = $stmt->fetch()['count'];
        
        if ($totalEvaluacionesDespues != $totalEvaluacionesAntes) {
            // Si cambió el número de evaluaciones, hacer rollback
            $pdo->rollBack();
            error_log("ERROR CRÍTICO: Se eliminaron evaluaciones del banco al eliminar la cita. Antes: " . $totalEvaluacionesAntes . ", Después: " . $totalEvaluacionesDespues);
            echo json_encode([
                'success' => false, 
                'message' => 'Error: No se puede eliminar la cita porque afectaría otras tablas. Contacte al administrador.'
            ]);
            return;
        }
        
        // Confirmar la transacción - SOLO se elimina la cita
        $pdo->commit();
        
        // Log para debug
        error_log("Cita eliminada correctamente. ID: " . $citaId . ", Solicitud ID: " . $solicitudId);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cita eliminada correctamente'
        ]);
        
    } catch (PDOException $e) {
        // En caso de error, revertir cualquier cambio
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        error_log("Error al eliminar cita: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar cita: ' . $e->getMessage()]);
    }
}
?>
