<?php
/**
 * API de reportes (solo administrador)
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'reporte_usuarios':
        reporteUsuarios();
        break;
    case 'solicitudes_usuario_estado':
        solicitudesPorUsuarioEstado();
        break;
    case 'reporte_tiempo':
        reporteTiempo();
        break;
    case 'historial_solicitud':
        historialSolicitud();
        break;
    case 'reporte_banco':
        reporteBanco();
        break;
    case 'reporte_emails_resumen':
        reporteEmailsResumen();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Total de solicitudes por usuario (gestor) y por estado
 */
function reporteUsuarios() {
    global $pdo;
    try {
        $sql = "
            SELECT 
                u.id as usuario_id,
                u.nombre,
                u.apellido,
                u.email,
                s.estado,
                COUNT(s.id) as total
            FROM usuarios u
            LEFT JOIN solicitudes_credito s ON s.gestor_id = u.id
            WHERE u.activo = 1
            AND EXISTS (
                SELECT 1 FROM usuario_roles ur 
                INNER JOIN roles r ON ur.rol_id = r.id 
                WHERE ur.usuario_id = u.id AND r.nombre IN ('ROLE_GESTOR', 'ROLE_ADMIN')
            )
            GROUP BY u.id, u.nombre, u.apellido, u.email, s.estado
            ORDER BY u.apellido, u.nombre, s.estado
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agrupar por usuario con totales por estado
        $porUsuario = [];
        foreach ($rows as $r) {
            $id = $r['usuario_id'];
            if (!isset($porUsuario[$id])) {
                $porUsuario[$id] = [
                    'usuario_id' => $id,
                    'nombre' => $r['nombre'] . ' ' . $r['apellido'],
                    'email' => $r['email'],
                    'Nueva' => 0,
                    'En Revisión Banco' => 0,
                    'Aprobada' => 0,
                    'Rechazada' => 0,
                    'Completada' => 0,
                    'Desistimiento' => 0,
                    'total' => 0
                ];
            }
            if ($r['estado']) {
                $porUsuario[$id][$r['estado']] = (int)$r['total'];
                $porUsuario[$id]['total'] += (int)$r['total'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => array_values($porUsuario)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Listado de solicitudes por gestor y estado (para el modal)
 */
function solicitudesPorUsuarioEstado() {
    global $pdo;
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    $estado = trim($_GET['estado'] ?? '');
    
    if (!$usuarioId || !$estado) {
        echo json_encode(['success' => false, 'message' => 'usuario_id y estado requeridos']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
            FROM solicitudes_credito s
            WHERE s.gestor_id = ? AND s.estado = ?
            ORDER BY s.fecha_actualizacion DESC
        ");
        $stmt->execute([$usuarioId, $estado]);
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $solicitudes]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Reporte tiempo: solicitudes con tiempo entre cambios de estado + columna acciones
 * Incluye datos para calcular tiempo en estado actual desde historial
 */
function reporteTiempo() {
    global $pdo;
    try {
        $solicitudes = $pdo->query("
            SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
            FROM solicitudes_credito s
            ORDER BY s.fecha_actualizacion DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($solicitudes as &$s) {
            $s['dias_en_estado_actual'] = null;
            $s['horas_en_estado_actual'] = null;
            if ($s['fecha_actualizacion']) {
                $stmt = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(DAY, ?, NOW()) as dias, TIMESTAMPDIFF(HOUR, ?, NOW()) as horas
                ");
                $stmt->execute([$s['fecha_actualizacion'], $s['fecha_actualizacion']]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $s['dias_en_estado_actual'] = (int)$r['dias'];
                $s['horas_en_estado_actual'] = (int)$r['horas'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $solicitudes]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Historial completo de una solicitud (para modal)
 */
function historialSolicitud() {
    global $pdo;
    $solicitudId = (int)($_GET['solicitud_id'] ?? 0);
    
    if (!$solicitudId) {
        echo json_encode(['success' => false, 'message' => 'solicitud_id requerido']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT h.id, h.tipo_accion, h.descripcion, h.estado_anterior, h.estado_nuevo, h.fecha_creacion,
                   u.nombre, u.apellido
            FROM historial_solicitud h
            LEFT JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.solicitud_id = ?
            ORDER BY h.fecha_creacion ASC
        ");
        $stmt->execute([$solicitudId]);
        $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [
            'creacion' => 'Creación',
            'cambio_estado' => 'Cambio de estado',
            'documento_agregado' => 'Documento agregado',
            'asignacion_banco' => 'Asignación a banco',
            'actualizacion_datos' => 'Actualización de datos',
            'evaluacion_banco' => 'Evaluación del banco'
        ];
        
        foreach ($historial as &$h) {
            $h['tipo_label'] = $labels[$h['tipo_accion']] ?? $h['tipo_accion'];
            $h['usuario_nombre'] = trim(($h['nombre'] ?? '') . ' ' . ($h['apellido'] ?? ''));
        }
        
        echo json_encode(['success' => true, 'data' => $historial]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Rep. Banco: tiempo que tardan los bancos en dar respuesta a las solicitudes asignadas.
 * Desde fecha_asignacion (usuarios_banco_solicitudes) hasta primera fecha_evaluacion (evaluaciones_banco).
 */
function reporteBanco() {
    global $pdo;
    try {
        $sql = "
            SELECT 
                s.id AS solicitud_id,
                s.nombre_cliente,
                s.cedula,
                s.estado,
                b.id AS banco_id,
                b.nombre AS banco_nombre,
                ubs.fecha_asignacion,
                MIN(eb.fecha_evaluacion) AS fecha_respuesta
            FROM solicitudes_credito s
            INNER JOIN usuarios_banco_solicitudes ubs ON ubs.solicitud_id = s.id
            INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
            LEFT JOIN bancos b ON b.id = u.banco_id
            LEFT JOIN evaluaciones_banco eb ON eb.solicitud_id = s.id AND eb.usuario_banco_id = ubs.id
            GROUP BY s.id, s.nombre_cliente, s.cedula, s.estado, b.id, b.nombre, ubs.id, ubs.fecha_asignacion
            ORDER BY ubs.fecha_asignacion DESC
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as &$r) {
            $r['dias_respuesta'] = null;
            $r['horas_respuesta'] = null;
            $r['pendiente'] = empty($r['fecha_respuesta']);
            if (!empty($r['fecha_respuesta']) && !empty($r['fecha_asignacion'])) {
                $stmt2 = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(DAY, ?, ?) AS dias, TIMESTAMPDIFF(HOUR, ?, ?) AS horas
                ");
                $stmt2->execute([$r['fecha_asignacion'], $r['fecha_respuesta'], $r['fecha_asignacion'], $r['fecha_respuesta']]);
                $d = $stmt2->fetch(PDO::FETCH_ASSOC);
                $r['dias_respuesta'] = (int)$d['dias'];
                $r['horas_respuesta'] = (int)$d['horas'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}

/**
 * Reporte de envíos de correos de resumen a banco.
 * Devuelve resumen (enviados/fallidos) y listado detallado.
 */
function reporteEmailsResumen() {
    global $pdo;
    try {
        $desde = trim((string)($_GET['desde'] ?? ''));
        $hasta = trim((string)($_GET['hasta'] ?? ''));
        $estado = trim((string)($_GET['estado'] ?? ''));

        $where = [];
        $params = [];

        if ($estado === 'enviado' || $estado === 'fallido') {
            $where[] = 'l.estado = ?';
            $params[] = $estado;
        }
        if ($desde !== '') {
            $where[] = 'DATE(l.fecha_envio) >= ?';
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where[] = 'DATE(l.fecha_envio) <= ?';
            $params[] = $hasta;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sqlResumen = "
            SELECT
                SUM(CASE WHEN l.estado = 'enviado' THEN 1 ELSE 0 END) AS enviados,
                SUM(CASE WHEN l.estado = 'fallido' THEN 1 ELSE 0 END) AS fallidos,
                COUNT(*) AS total
            FROM email_resumen_banco_log l
            {$whereSql}
        ";
        $stmtR = $pdo->prepare($sqlResumen);
        $stmtR->execute($params);
        $resumen = $stmtR->fetch(PDO::FETCH_ASSOC) ?: ['enviados' => 0, 'fallidos' => 0, 'total' => 0];

        $sqlDetalle = "
            SELECT
                l.id, l.solicitud_id, l.usuario_banco_id, l.destinatario_email, l.tipo_envio, l.estado,
                l.provider, l.provider_message_id, l.mensaje, l.fecha_envio,
                s.nombre_cliente
            FROM email_resumen_banco_log l
            LEFT JOIN solicitudes_credito s ON s.id = l.solicitud_id
            {$whereSql}
            ORDER BY l.fecha_envio DESC
            LIMIT 1000
        ";
        $stmtD = $pdo->prepare($sqlDetalle);
        $stmtD->execute($params);
        $detalle = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'resumen' => [
                'enviados' => (int)($resumen['enviados'] ?? 0),
                'fallidos' => (int)($resumen['fallidos'] ?? 0),
                'total' => (int)($resumen['total'] ?? 0),
            ],
            'data' => $detalle
        ]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1146) {
            echo json_encode([
                'success' => true,
                'resumen' => ['enviados' => 0, 'fallidos' => 0, 'total' => 0],
                'data' => []
            ]);
            return;
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
}
