<?php
session_start();

// Suprimir warnings de deprecación que pueden romper el JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);

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
        if (!empty($_GET['mis_propuestas']) && $_GET['mis_propuestas'] === '1') {
            listarMisPropuestasBanco();
        } elseif (isset($_GET['solicitud_id'])) {
            $usuarioBancoId = isset($_GET['usuario_banco_id']) ? $_GET['usuario_banco_id'] : null;
            obtenerEvaluaciones($_GET['solicitud_id'], $usuarioBancoId);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud requerido']);
        }
        break;
        
    case 'POST':
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'seleccionar_propuesta':
                    seleccionarPropuesta();
                    break;
                case 'solicitar_reevaluacion':
                    solicitarReevaluacion();
                    break;
                default:
                    guardarEvaluacion();
                    break;
            }
        } else {
            guardarEvaluacion();
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}

/**
 * Lista todas las evaluaciones (propuestas) emitidas por el usuario banco logueado, en todas las solicitudes.
 */
function listarMisPropuestasBanco(): void {
    global $pdo;

    if (!isset($_SESSION['user_roles']) || !in_array('ROLE_BANCO', $_SESSION['user_roles'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo usuarios banco pueden ver esta lista']);
        return;
    }

    $uid = (int) $_SESSION['user_id'];

    try {
        $sql = "
            SELECT
                e.*,
                s.id AS solicitud_id,
                s.nombre_cliente,
                s.cedula,
                s.estado AS solicitud_estado,
                s.evaluacion_seleccionada,
                (s.evaluacion_seleccionada = e.id) AS es_propuesta_seleccionada,
                v.marca AS vehiculo_marca,
                v.modelo AS vehiculo_modelo,
                v.anio AS vehiculo_anio
            FROM evaluaciones_banco e
            INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
            INNER JOIN solicitudes_credito s ON e.solicitud_id = s.id
            LEFT JOIN vehiculos_solicitud v ON e.vehiculo_id = v.id
            WHERE ubs.usuario_banco_id = ?
            ORDER BY e.fecha_evaluacion DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['es_propuesta_seleccionada'] = !empty($r['es_propuesta_seleccionada']);
        }
        unset($r);

        echo json_encode([
            'success' => true,
            'data' => $rows,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('listarMisPropuestasBanco: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al cargar sus propuestas']);
    }
}

function obtenerEvaluaciones($solicitudId, $usuarioBancoId = null) {
      global $pdo;
      
      try {
          // Primero obtener información sobre la evaluación seleccionada
          $stmt = $pdo->prepare("
              SELECT s.evaluacion_seleccionada,
                     s.nombre_cliente,
                     s.cedula,
                     (SELECT eb2.usuario_banco_id FROM evaluaciones_banco eb2 WHERE eb2.id = s.evaluacion_seleccionada) AS usuario_banco_id_seleccionado
              FROM solicitudes_credito s
              WHERE s.id = ?
          ");
          $stmt->execute([$solicitudId]);
          $solicitud = $stmt->fetch();

          if (!$solicitud) {
              echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
              return;
          }

          $evaluacionSeleccionada = $solicitud['evaluacion_seleccionada'] ?? null;
          $usuarioBancoIdSeleccionado = $solicitud['usuario_banco_id_seleccionado'] ?? null;
          
          $sql = "
              SELECT e.*, 
                     u.nombre, u.apellido,
                     v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio
              FROM evaluaciones_banco e
              LEFT JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
              LEFT JOIN usuarios u ON ubs.usuario_banco_id = u.id
              LEFT JOIN vehiculos_solicitud v ON e.vehiculo_id = v.id
              WHERE e.solicitud_id = ?
          ";
          
          $params = [$solicitudId];
          
          if ($usuarioBancoId) {
              $sql .= " AND ubs.usuario_banco_id = ?";
              $params[] = $usuarioBancoId;
          }
          
          $sql .= " ORDER BY e.fecha_evaluacion DESC";
          
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);
          $evaluaciones = $stmt->fetchAll();
          
          echo json_encode([
              'success' => true,
              'data' => $evaluaciones,
              'evaluacion_seleccionada' => $evaluacionSeleccionada,
              'usuario_banco_id_seleccionado' => $usuarioBancoIdSeleccionado,
              'solicitud_cliente' => [
                  'nombre_cliente' => $solicitud['nombre_cliente'] ?? '',
                  'cedula' => $solicitud['cedula'] ?? '',
              ],
          ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

function guardarEvaluacion() {
    global $pdo;
    
    try {
        // Validar campos requeridos
        if (empty($_POST['solicitud_id']) || empty($_POST['decision'])) {
            echo json_encode(['success' => false, 'message' => 'Solicitud ID y decisión son requeridos']);
            return;
        }
        
        $solicitudId = $_POST['solicitud_id'];
        $decision = $_POST['decision'];

        $tasaRaw = isset($_POST['tasa_bancaria']) ? trim((string) $_POST['tasa_bancaria']) : '';
        if ($tasaRaw === '' || !is_numeric($tasaRaw)) {
            echo json_encode(['success' => false, 'message' => 'La tasa bancaria (%) es obligatoria y debe ser un número']);
            return;
        }
        $tasaBancaria = round((float) $tasaRaw, 2);
        if ($tasaBancaria < 0 || $tasaBancaria > 100) {
            echo json_encode(['success' => false, 'message' => 'La tasa bancaria debe estar entre 0 y 100']);
            return;
        }
        
        // Verificar que el usuario es banco
        if (!isset($_SESSION['user_roles']) || !in_array('ROLE_BANCO', $_SESSION['user_roles'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para evaluar solicitudes']);
            return;
        }
        
        // Verificar que el usuario está asignado a esta solicitud
        $stmt = $pdo->prepare("
            SELECT id FROM usuarios_banco_solicitudes 
            WHERE solicitud_id = ? AND usuario_banco_id = ? AND estado = 'activo'
            LIMIT 1
        ");
        $stmt->execute([$solicitudId, $_SESSION['user_id']]);
        $asignacion = $stmt->fetch();
        
        if (!$asignacion) {
            echo json_encode(['success' => false, 'message' => 'No está asignado a esta solicitud']);
            return;
        }
        
        // Guardar evaluación en la tabla de historial
        $stmt = $pdo->prepare("
            INSERT INTO evaluaciones_banco 
            (solicitud_id, vehiculo_id, usuario_banco_id, decision, valor_financiar, abono, plazo, letra, promocion, tasa_bancaria, comentarios)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $solicitudId,
            !empty($_POST['vehiculo_evaluacion']) ? $_POST['vehiculo_evaluacion'] : null,
            $asignacion['id'],
            $decision,
            $_POST['valor_financiar'] ?? null,
            $_POST['abono_evaluacion'] ?? null,
            $_POST['plazo_evaluacion'] ?? null,
            $_POST['letra_evaluacion'] ?? null,
            $_POST['promocion_evaluacion'] ?? null,
            $tasaBancaria,
            $_POST['comentarios_evaluacion'] ?? null
        ]);
        
        $evaluacionId = $pdo->lastInsertId();
        
        // Actualizar la solicitud con la última decisión
        $stmt = $pdo->prepare("
            UPDATE solicitudes_credito 
            SET respuesta_banco = ?,
                letra = ?,
                plazo = ?,
                abono_banco = ?,
                promocion = ?,
                comentarios_ejecutivo_banco = ?,
                banco_id = ?
            WHERE id = ?
        ");
        
        // Convertir decisión a formato del ENUM
        $respuestaBancoEnum = '';
        switch($decision) {
            case 'preaprobado':
                $respuestaBancoEnum = 'Pre Aprobado';
                break;
            case 'aprobado':
                $respuestaBancoEnum = 'Aprobado';
                break;
            case 'aprobado_condicional':
                $respuestaBancoEnum = 'Aprobado Condicional';
                break;
            case 'rechazado':
                $respuestaBancoEnum = 'Rechazado';
                break;
        }
        
        $stmt->execute([
            $respuestaBancoEnum,
            $_POST['letra_evaluacion'] ?? null,
            $_POST['plazo_evaluacion'] ?? null,
            $_POST['abono_evaluacion'] ?? null,
            $_POST['promocion_evaluacion'] ?? null,
            $_POST['comentarios_evaluacion'] ?? null,
            $_SESSION['user_id'],
            $solicitudId
        ]);
        
        // Enviar notificación al vendedor si el banco respondió (solo si email_helper está disponible)
        try {
            require_once '../includes/email_helper.php';
            
            if (in_array($respuestaBancoEnum, ['Aprobado', 'Pre Aprobado', 'Rechazado', 'Aprobado Condicional'])) {
                $resultadoEmail = enviarNotificacionVendedor($solicitudId);
                // Log del resultado (no fallar si el correo no se envía)
                if (!$resultadoEmail['success']) {
                    error_log("No se pudo enviar correo al vendedor: " . $resultadoEmail['message']);
                }
                
                // Si está aprobada, también notificar al cliente
                if (in_array($respuestaBancoEnum, ['Aprobado', 'Pre Aprobado'])) {
                    $resultadoCliente = notificarClienteAprobacion($solicitudId);
                    if (!$resultadoCliente['success']) {
                        error_log("No se pudo enviar correo al cliente: " . $resultadoCliente['message']);
                    }
                }
            }
        } catch (Exception $e) {
            // Si no se puede cargar email_helper (Composer no instalado), continuar sin enviar correo
            error_log("No se pudo cargar email_helper: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Evaluación guardada correctamente',
            'data' => ['id' => $evaluacionId]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al guardar evaluación: ' . $e->getMessage()]);
    }
}

function seleccionarPropuesta() {
    global $pdo;
    
    try {
        // Verificar que el usuario es admin o gestor
        if (!isset($_SESSION['user_roles']) || (!in_array('ROLE_ADMIN', $_SESSION['user_roles']) && !in_array('ROLE_GESTOR', $_SESSION['user_roles']))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los administradores y gestores pueden seleccionar propuestas']);
            return;
        }
        
        // Validar campos
        if (empty($_POST['evaluacion_id']) || empty($_POST['solicitud_id']) || empty($_POST['comentario'])) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
            return;
        }
        
        $evaluacionId = $_POST['evaluacion_id'];
        $solicitudId = $_POST['solicitud_id'];
        $comentario = $_POST['comentario'];
        
        // Obtener la evaluación seleccionada
        $stmt = $pdo->prepare("SELECT * FROM evaluaciones_banco WHERE id = ?");
        $stmt->execute([$evaluacionId]);
        $evaluacion = $stmt->fetch();
        
        if (!$evaluacion) {
            echo json_encode(['success' => false, 'message' => 'Evaluación no encontrada']);
            return;
        }
        
                  // Actualizar la solicitud con la evaluación seleccionada
          $stmt = $pdo->prepare("
              UPDATE solicitudes_credito 
              SET evaluacion_seleccionada = ?,
                  fecha_aprobacion_propuesta = NOW(),
                  comentario_seleccion_propuesta = ?
              WHERE id = ?
          ");
          $stmt->execute([$evaluacionId, $comentario, $solicitudId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Propuesta seleccionada correctamente. Los demás bancos ya no podrán interactuar con esta solicitud.'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al seleccionar propuesta: ' . $e->getMessage()]);
    }
}

function solicitarReevaluacion() {
    global $pdo;
    
    try {
        // Verificar que el usuario es admin o gestor
        if (!isset($_SESSION['user_roles']) || (!in_array('ROLE_ADMIN', $_SESSION['user_roles']) && !in_array('ROLE_GESTOR', $_SESSION['user_roles']))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo los administradores y gestores pueden solicitar reevaluación']);
            return;
        }
        
        // Validar campos
        if (empty($_POST['evaluacion_id']) || empty($_POST['solicitud_id']) || empty($_POST['comentario'])) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
            return;
        }
        
        $evaluacionId = $_POST['evaluacion_id'];
        $solicitudId = $_POST['solicitud_id'];
        $comentario = $_POST['comentario'];
        
        // Obtener la evaluación
        $stmt = $pdo->prepare("SELECT * FROM evaluaciones_banco WHERE id = ?");
        $stmt->execute([$evaluacionId]);
        $evaluacion = $stmt->fetch();
        
        if (!$evaluacion) {
            echo json_encode(['success' => false, 'message' => 'Evaluación no encontrada']);
            return;
        }
        
        // Actualizar la solicitud marcando la evaluación para reevaluación
        $stmt = $pdo->prepare("
            UPDATE solicitudes_credito 
            SET evaluacion_en_reevaluacion = ?
            WHERE id = ?
        ");
        $stmt->execute([$evaluacionId, $solicitudId]);
        
        // Enviar notificación al banco sobre la reevaluación (solo si email_helper está disponible)
        try {
            require_once '../includes/email_helper.php';
            $resultadoEmail = notificarReevaluacion($solicitudId, $evaluacionId, $comentario);
            if (!$resultadoEmail['success']) {
                error_log("No se pudo enviar correo de reevaluación: " . $resultadoEmail['message']);
            }
        } catch (Exception $e) {
            // Si no se puede cargar email_helper (Composer no instalado), continuar sin enviar correo
            error_log("No se pudo cargar email_helper: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Reevaluación solicitada correctamente'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al solicitar reevaluación: ' . $e->getMessage()]);
    }
}

