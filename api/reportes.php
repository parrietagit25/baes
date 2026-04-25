<?php
/**
 * API de reportes (solo administrador)
 */

session_start();
$action = $_GET['action'] ?? '';

if (!isset($_SESSION['user_id']) || !in_array('ROLE_ADMIN', $_SESSION['user_roles'] ?? [])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($action === 'exportar_todos_excel') {
    exportarTodosReportesExcel();
    exit();
}
if (in_array($action, [
    'exportar_excel_usuarios',
    'exportar_excel_tiempo',
    'exportar_excel_banco',
    'exportar_excel_correos',
    'exportar_excel_encuestas_vendedores',
    'exportar_excel_encuestas_gestores',
], true)) {
    exportarReporteCsv($action);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

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
    case 'reporte_encuestas':
        reporteEncuestas();
        break;
    case 'exportar_todos_excel':
        // Ya atendido al inicio.
        echo json_encode(['success' => false, 'message' => 'Acción ya ejecutada']);
        break;
    case 'exportar_excel_usuarios':
    case 'exportar_excel_tiempo':
    case 'exportar_excel_banco':
    case 'exportar_excel_correos':
    case 'exportar_excel_encuestas_vendedores':
    case 'exportar_excel_encuestas_gestores':
        // Ya atendido al inicio.
        echo json_encode(['success' => false, 'message' => 'Acción ya ejecutada']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function exportarReporteCsv(string $action): void {
    global $pdo;
    require_once __DIR__ . '/../includes/encuestas_satisfaccion_data.php';

    if ($action === 'exportar_excel_usuarios') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['usuario_id'] ?? '',
                $r['nombre'] ?? '',
                $r['email'] ?? '',
                $r['Nueva'] ?? 0,
                $r['En Revisión Banco'] ?? 0,
                $r['Aprobada'] ?? 0,
                $r['Rechazada'] ?? 0,
                $r['Completada'] ?? 0,
                $r['Desistimiento'] ?? 0,
                $r['total'] ?? 0,
            ];
        }, _dataReporteUsuarios($pdo));
        _outputCsvDownload('reporte_usuarios.csv', [
            'Usuario ID', 'Nombre', 'Email', 'Nueva', 'En Revision Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento', 'Total'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_tiempo') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['nombre_cliente'] ?? '',
                $r['cedula'] ?? '',
                $r['estado'] ?? '',
                $r['fecha_creacion'] ?? '',
                $r['fecha_actualizacion'] ?? '',
                $r['dias_en_estado_actual'] ?? '',
                $r['horas_en_estado_actual'] ?? '',
            ];
        }, _dataReporteTiempo($pdo));
        _outputCsvDownload('reporte_tiempo.csv', [
            'Solicitud ID', 'Cliente', 'Cedula', 'Estado', 'Fecha Creacion', 'Fecha Actualizacion', 'Dias Estado Actual', 'Horas Estado Actual'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_banco') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['solicitud_id'] ?? '',
                $r['nombre_cliente'] ?? '',
                $r['cedula'] ?? '',
                $r['estado'] ?? '',
                $r['banco_nombre'] ?? '',
                $r['fecha_asignacion'] ?? '',
                $r['fecha_respuesta'] ?? '',
                !empty($r['pendiente']) ? 'Si' : 'No',
                $r['dias_respuesta'] ?? '',
                $r['horas_respuesta'] ?? '',
            ];
        }, _dataReporteBanco($pdo));
        _outputCsvDownload('reporte_banco.csv', [
            'Solicitud ID', 'Cliente', 'Cedula', 'Estado Solicitud', 'Banco', 'Fecha Asignacion', 'Fecha Respuesta', 'Pendiente', 'Dias Respuesta', 'Horas Respuesta'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_correos') {
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['solicitud_id'] ?? '',
                $r['nombre_cliente'] ?? '',
                $r['destinatario_email'] ?? '',
                $r['tipo_envio'] ?? '',
                $r['estado'] ?? '',
                $r['provider'] ?? '',
                $r['provider_message_id'] ?? '',
                $r['mensaje'] ?? '',
                $r['fecha_envio'] ?? '',
            ];
        }, _dataReporteEmails($pdo));
        _outputCsvDownload('reporte_correos.csv', [
            'ID', 'Solicitud ID', 'Cliente', 'Destinatario', 'Tipo Envio', 'Estado', 'Provider', 'Provider Message ID', 'Mensaje', 'Fecha Envio'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_encuestas_vendedores') {
        $enc = _reporteEncuestasBloque($pdo, 'encuesta_formulario_publico_vendedor', $ENCUESTA_VENDEDOR_PREGUNTAS);
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['creado_en'] ?? '',
                $r['nombre_completo'] ?? '',
                $r['cargo'] ?? '',
                $r['puntuacion_1'] ?? '',
                $r['puntuacion_2'] ?? '',
                $r['puntuacion_3'] ?? '',
                $r['puntuacion_4'] ?? '',
                $r['puntuacion_5'] ?? '',
                $r['promedio_fila'] ?? '',
                $r['recomendaciones'] ?? '',
            ];
        }, $enc['filas'] ?? []);
        _outputCsvDownload('reporte_encuestas_vendedores.csv', [
            'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
        ], $rows);
        return;
    }

    if ($action === 'exportar_excel_encuestas_gestores') {
        $enc = _reporteEncuestasBloque($pdo, 'encuesta_proceso_gestor', $ENCUESTA_GESTOR_PREGUNTAS);
        $rows = array_map(static function(array $r): array {
            return [
                $r['id'] ?? '',
                $r['creado_en'] ?? '',
                $r['nombre_completo'] ?? '',
                $r['cargo'] ?? '',
                $r['puntuacion_1'] ?? '',
                $r['puntuacion_2'] ?? '',
                $r['puntuacion_3'] ?? '',
                $r['puntuacion_4'] ?? '',
                $r['puntuacion_5'] ?? '',
                $r['promedio_fila'] ?? '',
                $r['recomendaciones'] ?? '',
            ];
        }, $enc['filas'] ?? []);
        _outputCsvDownload('reporte_encuestas_gestores.csv', [
            'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
        ], $rows);
        return;
    }
}

function _outputCsvDownload(string $fileName, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    $fp = fopen('php://output', 'w');
    if ($fp === false) {
        return;
    }
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers, ';');
    foreach ($rows as $row) {
        $safe = [];
        foreach ($row as $value) {
            if (is_bool($value)) {
                $safe[] = $value ? '1' : '0';
            } elseif ($value === null) {
                $safe[] = '';
            } else {
                $safe[] = (string) $value;
            }
        }
        fputcsv($fp, $safe, ';');
    }
    fclose($fp);
}

function exportarTodosReportesExcel() {
    global $pdo;
    require_once __DIR__ . '/../includes/encuestas_satisfaccion_data.php';
    $tmpZip = tempnam(sys_get_temp_dir(), 'rep_motus_');
    if ($tmpZip === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo crear archivo temporal']);
        return;
    }

    $zipPath = $tmpZip . '.zip';
    @rename($tmpZip, $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($zipPath);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No se pudo crear ZIP de exportación']);
        return;
    }

    $usuarios = _dataReporteUsuarios($pdo);
    _zipAddCsv($zip, 'reporte_usuarios.csv', [
        'Usuario ID', 'Nombre', 'Email', 'Nueva', 'En Revision Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento', 'Total'
    ], array_map(static function(array $r): array {
        return [
            $r['usuario_id'] ?? '',
            $r['nombre'] ?? '',
            $r['email'] ?? '',
            $r['Nueva'] ?? 0,
            $r['En Revisión Banco'] ?? 0,
            $r['Aprobada'] ?? 0,
            $r['Rechazada'] ?? 0,
            $r['Completada'] ?? 0,
            $r['Desistimiento'] ?? 0,
            $r['total'] ?? 0,
        ];
    }, $usuarios));

    $tiempo = _dataReporteTiempo($pdo);
    _zipAddCsv($zip, 'reporte_tiempo.csv', [
        'Solicitud ID', 'Cliente', 'Cedula', 'Estado', 'Fecha Creacion', 'Fecha Actualizacion', 'Dias Estado Actual', 'Horas Estado Actual'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['cedula'] ?? '',
            $r['estado'] ?? '',
            $r['fecha_creacion'] ?? '',
            $r['fecha_actualizacion'] ?? '',
            $r['dias_en_estado_actual'] ?? '',
            $r['horas_en_estado_actual'] ?? '',
        ];
    }, $tiempo));

    $banco = _dataReporteBanco($pdo);
    _zipAddCsv($zip, 'reporte_banco.csv', [
        'Solicitud ID', 'Cliente', 'Cedula', 'Estado Solicitud', 'Banco', 'Fecha Asignacion', 'Fecha Respuesta', 'Pendiente', 'Dias Respuesta', 'Horas Respuesta'
    ], array_map(static function(array $r): array {
        return [
            $r['solicitud_id'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['cedula'] ?? '',
            $r['estado'] ?? '',
            $r['banco_nombre'] ?? '',
            $r['fecha_asignacion'] ?? '',
            $r['fecha_respuesta'] ?? '',
            !empty($r['pendiente']) ? 'Si' : 'No',
            $r['dias_respuesta'] ?? '',
            $r['horas_respuesta'] ?? '',
        ];
    }, $banco));

    $emails = _dataReporteEmails($pdo);
    _zipAddCsv($zip, 'reporte_correos.csv', [
        'ID', 'Solicitud ID', 'Cliente', 'Destinatario', 'Tipo Envio', 'Estado', 'Provider', 'Provider Message ID', 'Mensaje', 'Fecha Envio'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['solicitud_id'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['destinatario_email'] ?? '',
            $r['tipo_envio'] ?? '',
            $r['estado'] ?? '',
            $r['provider'] ?? '',
            $r['provider_message_id'] ?? '',
            $r['mensaje'] ?? '',
            $r['fecha_envio'] ?? '',
        ];
    }, $emails));

    $encV = _reporteEncuestasBloque($pdo, 'encuesta_formulario_publico_vendedor', $ENCUESTA_VENDEDOR_PREGUNTAS);
    $encG = _reporteEncuestasBloque($pdo, 'encuesta_proceso_gestor', $ENCUESTA_GESTOR_PREGUNTAS);
    _zipAddCsv($zip, 'reporte_encuestas_vendedores.csv', [
        'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['creado_en'] ?? '',
            $r['nombre_completo'] ?? '',
            $r['cargo'] ?? '',
            $r['puntuacion_1'] ?? '',
            $r['puntuacion_2'] ?? '',
            $r['puntuacion_3'] ?? '',
            $r['puntuacion_4'] ?? '',
            $r['puntuacion_5'] ?? '',
            $r['promedio_fila'] ?? '',
            $r['recomendaciones'] ?? '',
        ];
    }, $encV['filas'] ?? []));
    _zipAddCsv($zip, 'reporte_encuestas_gestores.csv', [
        'ID', 'Fecha', 'Nombre Completo', 'Cargo', 'P1', 'P2', 'P3', 'P4', 'P5', 'Promedio', 'Recomendaciones'
    ], array_map(static function(array $r): array {
        return [
            $r['id'] ?? '',
            $r['creado_en'] ?? '',
            $r['nombre_completo'] ?? '',
            $r['cargo'] ?? '',
            $r['puntuacion_1'] ?? '',
            $r['puntuacion_2'] ?? '',
            $r['puntuacion_3'] ?? '',
            $r['puntuacion_4'] ?? '',
            $r['puntuacion_5'] ?? '',
            $r['promedio_fila'] ?? '',
            $r['recomendaciones'] ?? '',
        ];
    }, $encG['filas'] ?? []));

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="reportes_motus_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . (string) filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
}

function _zipAddCsv(ZipArchive $zip, string $fileName, array $headers, array $rows): void {
    $fp = fopen('php://temp', 'r+');
    if ($fp === false) {
        return;
    }
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers, ';');
    foreach ($rows as $row) {
        $safe = [];
        foreach ($row as $value) {
            if (is_bool($value)) {
                $safe[] = $value ? '1' : '0';
            } elseif ($value === null) {
                $safe[] = '';
            } else {
                $safe[] = (string) $value;
            }
        }
        fputcsv($fp, $safe, ';');
    }
    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    if ($csv !== false) {
        $zip->addFromString($fileName, $csv);
    }
}

function _dataReporteUsuarios(PDO $pdo): array {
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
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $porUsuario = [];
    foreach ($rows as $r) {
        $id = $r['usuario_id'];
        if (!isset($porUsuario[$id])) {
            $porUsuario[$id] = [
                'usuario_id' => $id,
                'nombre' => trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
                'email' => $r['email'],
                'Nueva' => 0,
                'En Revisión Banco' => 0,
                'Aprobada' => 0,
                'Rechazada' => 0,
                'Completada' => 0,
                'Desistimiento' => 0,
                'total' => 0,
            ];
        }
        if (!empty($r['estado'])) {
            $porUsuario[$id][$r['estado']] = (int) $r['total'];
            $porUsuario[$id]['total'] += (int) $r['total'];
        }
    }
    return array_values($porUsuario);
}

function _dataReporteTiempo(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT s.id, s.nombre_cliente, s.cedula, s.estado, s.fecha_creacion, s.fecha_actualizacion
        FROM solicitudes_credito s
        ORDER BY s.fecha_actualizacion DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['dias_en_estado_actual'] = null;
        $r['horas_en_estado_actual'] = null;
        if (!empty($r['fecha_actualizacion'])) {
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(DAY, ?, NOW()) as dias, TIMESTAMPDIFF(HOUR, ?, NOW()) as horas");
            $st->execute([$r['fecha_actualizacion'], $r['fecha_actualizacion']]);
            $d = $st->fetch(PDO::FETCH_ASSOC);
            $r['dias_en_estado_actual'] = (int) ($d['dias'] ?? 0);
            $r['horas_en_estado_actual'] = (int) ($d['horas'] ?? 0);
        }
    }
    unset($r);
    return $rows;
}

function _dataReporteBanco(PDO $pdo): array {
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
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['dias_respuesta'] = null;
        $r['horas_respuesta'] = null;
        $r['pendiente'] = empty($r['fecha_respuesta']);
        if (!$r['pendiente'] && !empty($r['fecha_asignacion'])) {
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(DAY, ?, ?) AS dias, TIMESTAMPDIFF(HOUR, ?, ?) AS horas");
            $st->execute([$r['fecha_asignacion'], $r['fecha_respuesta'], $r['fecha_asignacion'], $r['fecha_respuesta']]);
            $d = $st->fetch(PDO::FETCH_ASSOC);
            $r['dias_respuesta'] = (int) ($d['dias'] ?? 0);
            $r['horas_respuesta'] = (int) ($d['horas'] ?? 0);
        }
    }
    unset($r);
    return $rows;
}

function _dataReporteEmails(PDO $pdo): array {
    $sql = "
        SELECT
            l.id, l.solicitud_id, l.usuario_banco_id, l.destinatario_email, l.tipo_envio, l.estado,
            l.provider, l.provider_message_id, l.mensaje, l.fecha_envio,
            s.nombre_cliente
        FROM email_resumen_banco_log l
        LEFT JOIN solicitudes_credito s ON s.id = l.solicitud_id
        ORDER BY l.fecha_envio DESC
        LIMIT 1000
    ";
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1146) {
            return [];
        }
        throw $e;
    }
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

/**
 * Resumen y detalle de encuestas (formulario público vendedores y proceso gestor)
 */
function reporteEncuestas() {
    global $pdo;
    require_once __DIR__ . '/../includes/encuestas_satisfaccion_data.php';

    $vendedor = _reporteEncuestasBloque($pdo, 'encuesta_formulario_publico_vendedor', $ENCUESTA_VENDEDOR_PREGUNTAS);
    $gestor = _reporteEncuestasBloque($pdo, 'encuesta_proceso_gestor', $ENCUESTA_GESTOR_PREGUNTAS);

    echo json_encode([
        'success' => true,
        'vendedor' => $vendedor,
        'gestor' => $gestor,
    ]);
}

/**
 * @param array<int,string> $preguntas
 * @return array{resumen: ?array, filas: array, error: ?string, preguntas: array}
 */
function _reporteEncuestasBloque(PDO $pdo, string $table, array $preguntas) {
    $vacio = [
        'resumen' => null,
        'filas' => [],
        'error' => null,
        'preguntas' => $preguntas,
    ];

    $sqlResumen = "
        SELECT
            COUNT(*) AS total,
            AVG((puntuacion_1 + puntuacion_2 + puntuacion_3 + puntuacion_4 + puntuacion_5) / 5.0) AS promedio_global,
            AVG(puntuacion_1) AS promedio_p1,
            AVG(puntuacion_2) AS promedio_p2,
            AVG(puntuacion_3) AS promedio_p3,
            AVG(puntuacion_4) AS promedio_p4,
            AVG(puntuacion_5) AS promedio_p5,
            MIN(creado_en) AS desde,
            MAX(creado_en) AS hasta,
            SUM(
                CASE
                    WHEN recomendaciones IS NULL OR TRIM(recomendaciones) = '' THEN 0
                    ELSE 1
                END
            ) AS con_recomendacion
        FROM `{$table}`
    ";

    $sqlFilas = "
        SELECT
            id, nombre_completo, cargo,
            puntuacion_1, puntuacion_2, puntuacion_3, puntuacion_4, puntuacion_5,
            recomendaciones, creado_en
        FROM `{$table}`
        ORDER BY creado_en DESC
        LIMIT 2000
    ";

    try {
        $r = $pdo->query($sqlResumen)->fetch(PDO::FETCH_ASSOC);
        $total = (int) ($r['total'] ?? 0);
        if ($total === 0) {
            $vacio['resumen'] = [
                'total' => 0,
                'promedio_global' => null,
                'promedios' => [1 => null, 2 => null, 3 => null, 4 => null, 5 => null],
                'desde' => null,
                'hasta' => null,
                'con_recomendacion' => 0,
            ];
        } else {
            $vacio['resumen'] = [
                'total' => $total,
                'promedio_global' => $r['promedio_global'] !== null
                    ? round((float) $r['promedio_global'], 2)
                    : null,
                'promedios' => [
                    1 => $r['promedio_p1'] !== null ? round((float) $r['promedio_p1'], 2) : null,
                    2 => $r['promedio_p2'] !== null ? round((float) $r['promedio_p2'], 2) : null,
                    3 => $r['promedio_p3'] !== null ? round((float) $r['promedio_p3'], 2) : null,
                    4 => $r['promedio_p4'] !== null ? round((float) $r['promedio_p4'], 2) : null,
                    5 => $r['promedio_p5'] !== null ? round((float) $r['promedio_p5'], 2) : null,
                ],
                'desde' => $r['desde'] ? (string) $r['desde'] : null,
                'hasta' => $r['hasta'] ? (string) $r['hasta'] : null,
                'con_recomendacion' => (int) ($r['con_recomendacion'] ?? 0),
            ];
        }

        $filas = $pdo->query($sqlFilas)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $p1 = (int) $f['puntuacion_1'];
            $p2 = (int) $f['puntuacion_2'];
            $p3 = (int) $f['puntuacion_3'];
            $p4 = (int) $f['puntuacion_4'];
            $p5 = (int) $f['puntuacion_5'];
            $f['promedio_fila'] = round(($p1 + $p2 + $p3 + $p4 + $p5) / 5.0, 2);
        }
        unset($f);
        $vacio['filas'] = $filas;
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1146) {
            $vacio['error'] = 'Aún no existe la tabla. Ejecute database/migracion_encuestas_satisfaccion.sql en la base de datos.';
        } else {
            $vacio['error'] = 'Error al leer las encuestas.';
        }
    }

    return $vacio;
}
