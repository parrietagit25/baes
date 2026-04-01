<?php
/**
 * Cronología unificada de una solicitud (historial, muro, adjuntos, banco, etc.)
 */
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$solicitudId = isset($_GET['solicitud_id']) ? (int) $_GET['solicitud_id'] : 0;
if ($solicitudId <= 0) {
    echo json_encode(['success' => false, 'message' => 'solicitud_id inválido']);
    exit();
}

$roles = $_SESSION['user_roles'] ?? [];

if (!in_array('ROLE_ADMIN', $roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo el administrador puede ver la cronología']);
    exit();
}

$labelsHistorial = [
    'creacion' => 'Creación',
    'cambio_estado' => 'Cambio de estado',
    'documento_agregado' => 'Documento agregado',
    'asignacion_banco' => 'Asignación / banco',
    'actualizacion_datos' => 'Actualización de datos',
    'evaluacion_banco' => 'Evaluación del banco',
];

function tableExists(PDO $pdo, string $name): bool
{
    $q = $pdo->quote($name);
    $st = $pdo->query("SHOW TABLES LIKE $q");
    return $st && $st->rowCount() > 0;
}

function pushEvent(array &$events, string $fecha, string $tipo, string $tipoLabel, string $titulo, string $detalle, string $usuarioNombre): void
{
    if ($fecha === '' || $fecha === null) {
        return;
    }
    $events[] = [
        'fecha' => $fecha,
        'tipo' => $tipo,
        'tipo_label' => $tipoLabel,
        'titulo' => $titulo,
        'detalle' => $detalle,
        'usuario_nombre' => $usuarioNombre !== '' ? $usuarioNombre : 'Sistema',
    ];
}

function nombreUsuario(?string $n, ?string $a): string
{
    return trim(($n ?? '') . ' ' . ($a ?? ''));
}

$stmtExiste = $pdo->prepare('SELECT 1 FROM solicitudes_credito WHERE id = ?');
$stmtExiste->execute([$solicitudId]);
if (!$stmtExiste->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
    exit();
}

$events = [];

try {
    // --- Historial explícito ---
    if (tableExists($pdo, 'historial_solicitud')) {
        $stmt = $pdo->prepare(
            'SELECT h.tipo_accion, h.descripcion, h.estado_anterior, h.estado_nuevo, h.fecha_creacion,
                    u.nombre, u.apellido
             FROM historial_solicitud h
             LEFT JOIN usuarios u ON h.usuario_id = u.id
             WHERE h.solicitud_id = ?'
        );
        $stmt->execute([$solicitudId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $extra = '';
            if (!empty($h['estado_anterior']) || !empty($h['estado_nuevo'])) {
                $extra = ' (' . ($h['estado_anterior'] ?? '—') . ' → ' . ($h['estado_nuevo'] ?? '—') . ')';
            }
            pushEvent(
                $events,
                $h['fecha_creacion'],
                'historial',
                $labelsHistorial[$h['tipo_accion']] ?? $h['tipo_accion'],
                $labelsHistorial[$h['tipo_accion']] ?? $h['tipo_accion'],
                ($h['descripcion'] ?? '') . $extra,
                nombreUsuario($h['nombre'] ?? '', $h['apellido'] ?? '')
            );
        }
    }

    // --- Notas del muro ---
    if (tableExists($pdo, 'notas_solicitud')) {
        $stmt = $pdo->prepare(
            'SELECT n.tipo_nota, n.titulo, n.contenido, n.fecha_creacion, u.nombre, u.apellido
             FROM notas_solicitud n
             LEFT JOIN usuarios u ON n.usuario_id = u.id
             WHERE n.solicitud_id = ?'
        );
        $stmt->execute([$solicitudId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
            pushEvent(
                $events,
                $n['fecha_creacion'],
                'nota',
                'Muro / nota',
                ($n['tipo_nota'] ?? 'Nota') . ': ' . ($n['titulo'] ?? ''),
                $n['contenido'] ?? '',
                nombreUsuario($n['nombre'] ?? '', $n['apellido'] ?? '')
            );
        }
    }

    // --- Adjuntos ---
    if (tableExists($pdo, 'adjuntos_solicitud')) {
        $stmt = $pdo->prepare(
            'SELECT a.nombre_original, a.tipo_archivo, a.descripcion, a.fecha_subida, u.nombre, u.apellido
             FROM adjuntos_solicitud a
             LEFT JOIN usuarios u ON a.usuario_id = u.id
             WHERE a.solicitud_id = ?'
        );
        $stmt->execute([$solicitudId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $desc = $a['nombre_original'] ?? '';
            if (!empty($a['tipo_archivo'])) {
                $desc .= ' (' . $a['tipo_archivo'] . ')';
            }
            if (!empty($a['descripcion'])) {
                $desc .= "\n" . $a['descripcion'];
            }
            pushEvent(
                $events,
                $a['fecha_subida'],
                'adjunto',
                'Archivo adjunto',
                'Subió un archivo',
                trim($desc),
                nombreUsuario($a['nombre'] ?? '', $a['apellido'] ?? '')
            );
        }
    }

    // --- Documentos (tabla alternativa) ---
    if (tableExists($pdo, 'documentos_solicitud')) {
        $stmt = $pdo->prepare(
            'SELECT d.nombre_archivo, d.tipo_documento, d.fecha_subida, u.nombre, u.apellido
             FROM documentos_solicitud d
             LEFT JOIN usuarios u ON d.usuario_id = u.id
             WHERE d.solicitud_id = ?'
        );
        $stmt->execute([$solicitudId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $det = $d['nombre_archivo'] ?? '';
            if (!empty($d['tipo_documento'])) {
                $det .= ' — ' . $d['tipo_documento'];
            }
            pushEvent(
                $events,
                $d['fecha_subida'],
                'documento',
                'Documento',
                'Documento cargado',
                $det,
                nombreUsuario($d['nombre'] ?? '', $d['apellido'] ?? '')
            );
        }
    }

    // --- Mensajes ---
    if (tableExists($pdo, 'mensajes_solicitud')) {
        $stmt = $pdo->prepare(
            'SELECT m.mensaje, m.tipo, m.fecha_creacion, u.nombre, u.apellido
             FROM mensajes_solicitud m
             LEFT JOIN usuarios u ON m.usuario_id = u.id
             WHERE m.solicitud_id = ?'
        );
        $stmt->execute([$solicitudId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            pushEvent(
                $events,
                $m['fecha_creacion'],
                'mensaje',
                'Mensaje',
                'Mensaje (' . ($m['tipo'] ?? 'general') . ')',
                $m['mensaje'] ?? '',
                nombreUsuario($m['nombre'] ?? '', $m['apellido'] ?? '')
            );
        }
    }

    // --- Evaluaciones y reevaluaciones ---
    if (tableExists($pdo, 'evaluaciones_banco')) {
        $stmt = $pdo->prepare(
            'SELECT e.*, ub.nombre AS bn, ub.apellido AS ba, b.nombre AS inst
             FROM evaluaciones_banco e
             INNER JOIN usuarios_banco_solicitudes ubs ON e.usuario_banco_id = ubs.id
             INNER JOIN usuarios ub ON ubs.usuario_banco_id = ub.id
             LEFT JOIN bancos b ON ub.banco_id = b.id
             WHERE e.solicitud_id = ?'
        );
        $stmt->execute([$solicitudId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $bancoNom = nombreUsuario($e['bn'] ?? '', $e['ba'] ?? '');
            if (!empty($e['inst'])) {
                $bancoNom .= ' (' . $e['inst'] . ')';
            }
            $dec = strtoupper(str_replace('_', ' ', (string) ($e['decision'] ?? '')));
            $det = 'Decisión: ' . $dec;
            if (isset($e['tasa_bancaria']) && $e['tasa_bancaria'] !== '' && $e['tasa_bancaria'] !== null) {
                $det .= '. Tasa: ' . $e['tasa_bancaria'] . '%';
            }
            if (!empty($e['comentarios'])) {
                $det .= "\n" . $e['comentarios'];
            }
            pushEvent(
                $events,
                $e['fecha_evaluacion'],
                'evaluacion',
                'Respuesta del banco',
                'Evaluación / propuesta del banco',
                trim($det),
                $bancoNom
            );

            if (!empty($e['fecha_solicitud_reevaluacion']) && !empty($e['comentario_reevaluacion_solicitada'])) {
                pushEvent(
                    $events,
                    $e['fecha_solicitud_reevaluacion'],
                    'reevaluacion',
                    'Reevaluación',
                    'Solicitud de reevaluación al banco',
                    $e['comentario_reevaluacion_solicitada'],
                    'Gestor / administrador'
                );
            }
        }
    }

    // --- Propuesta seleccionada (no siempre en historial) ---
    $stmt = $pdo->prepare(
        'SELECT fecha_aprobacion_propuesta, comentario_seleccion_propuesta, evaluacion_seleccionada, fecha_creacion
         FROM solicitudes_credito WHERE id = ?'
    );
    $stmt->execute([$solicitudId]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sol && !empty($sol['fecha_aprobacion_propuesta']) && !empty($sol['evaluacion_seleccionada'])) {
        $det = trim((string) ($sol['comentario_seleccion_propuesta'] ?? ''));
        if ($det === '') {
            $det = 'Propuesta #' . (int) $sol['evaluacion_seleccionada'] . ' marcada como seleccionada.';
        } else {
            $det = 'Propuesta #' . (int) $sol['evaluacion_seleccionada'] . ".\n" . $det;
        }
        pushEvent(
            $events,
            $sol['fecha_aprobacion_propuesta'],
            'propuesta_seleccionada',
            'Propuesta seleccionada',
            'Selección de propuesta bancaria',
            $det,
            'Gestor / administrador'
        );
    }

    // --- Alta de solicitud si no hay registro en historial ---
    if ($sol && tableExists($pdo, 'historial_solicitud')) {
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM historial_solicitud WHERE solicitud_id = ? AND tipo_accion = 'creacion'"
        );
        $chk->execute([$solicitudId]);
        $tieneCreacion = (int) $chk->fetchColumn() > 0;
        if (!$tieneCreacion && !empty($sol['fecha_creacion'])) {
            $stmtG = $pdo->prepare(
                'SELECT u.nombre, u.apellido FROM solicitudes_credito s
                 LEFT JOIN usuarios u ON s.gestor_id = u.id WHERE s.id = ?'
            );
            $stmtG->execute([$solicitudId]);
            $g = $stmtG->fetch(PDO::FETCH_ASSOC);
            pushEvent(
                $events,
                $sol['fecha_creacion'],
                'alta_solicitud',
                'Alta',
                'Solicitud registrada',
                'Registro inicial de la solicitud en el sistema.',
                nombreUsuario($g['nombre'] ?? '', $g['apellido'] ?? '')
            );
        }
    }
} catch (PDOException $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al armar la cronología']);
    error_log('cronologia_solicitud: ' . $ex->getMessage());
    exit();
}

// Orden cronológico (más antiguo primero)
usort($events, static function ($a, $b) {
    return strcmp($a['fecha'] ?? '', $b['fecha'] ?? '');
});

echo json_encode([
    'success' => true,
    'data' => $events,
    'solicitud_id' => $solicitudId,
]);
