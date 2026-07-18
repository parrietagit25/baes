<?php
/**
 * API panel realtime de feria.
 * Filas: financiamiento_registros de vendedores asignados a la feria (ventana de fechas),
 * enriquecidas con solicitud de crédito si existe vínculo.
 * Solo Admin / Gestor. Solo lectura.
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

$roles = $_SESSION['user_roles'] ?? [];
if (!in_array('ROLE_ADMIN', $roles, true) && !in_array('ROLE_GESTOR', $roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/solicitud_vehiculo_helper.php';

$feriaId = isset($_GET['feria_id']) ? (int) $_GET['feria_id'] : 0;
if ($feriaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'feria_id requerido']);
    exit();
}

$since = isset($_GET['since']) ? trim((string) $_GET['since']) : '';

try {
    $stmt = $pdo->prepare('SELECT * FROM ferias WHERE id = ?');
    $stmt->execute([$feriaId]);
    $feria = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$feria) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Feria no encontrada']);
        exit();
    }

    $tieneFrScId = columnaExiste($pdo, 'financiamiento_registros', 'solicitud_credito_id');
    $tieneScFrId = columnaExiste($pdo, 'solicitudes_credito', 'financiamiento_registro_id');
    $tieneIdVendedor = columnaExiste($pdo, 'financiamiento_registros', 'id_vendedor');
    $tieneEmailVendedor = columnaExiste($pdo, 'financiamiento_registros', 'email_vendedor');

    if (!$tieneIdVendedor && !$tieneEmailVendedor) {
        echo json_encode([
            'success' => true,
            'changed' => true,
            'fingerprint' => '0',
            'feria' => resumenFeria($feria),
            'stats' => statsVacios(),
            'data' => [],
        ]);
        exit();
    }

    $desde = $feria['fecha_inicio'] . ' 00:00:00';
    $hasta = $feria['fecha_fin'] . ' 23:59:59';

    // Vendedores de la feria
    $stmtEv = $pdo->prepare('
        SELECT ev.id, ev.nombre, ev.email
        FROM feria_vendedores fv
        INNER JOIN ejecutivos_ventas ev ON ev.id = fv.ejecutivo_ventas_id
        WHERE fv.feria_id = ?
    ');
    $stmtEv->execute([$feriaId]);
    $vendedores = $stmtEv->fetchAll(PDO::FETCH_ASSOC);
    $evIds = [];
    $emails = [];
    $evById = [];
    $evByEmail = [];
    foreach ($vendedores as $ev) {
        $id = (int) $ev['id'];
        $evIds[] = $id;
        $evById[$id] = $ev;
        $em = strtolower(trim((string) ($ev['email'] ?? '')));
        if ($em !== '') {
            $emails[] = $em;
            $evByEmail[$em] = $ev;
        }
    }

    if (empty($evIds)) {
        $fp = fingerprintVacio($feria);
        if ($since !== '' && hash_equals($since, $fp)) {
            echo json_encode(['success' => true, 'changed' => false, 'fingerprint' => $fp]);
            exit();
        }
        echo json_encode([
            'success' => true,
            'changed' => true,
            'fingerprint' => $fp,
            'feria' => resumenFeria($feria),
            'stats' => statsVacios(),
            'data' => [],
        ]);
        exit();
    }

    $conds = [];
    $params = [$desde, $hasta];
    if ($tieneIdVendedor) {
        $ph = implode(',', array_fill(0, count($evIds), '?'));
        $conds[] = "fr.id_vendedor IN ($ph)";
        foreach ($evIds as $id) {
            $params[] = $id;
        }
    }
    if ($tieneEmailVendedor && !empty($emails)) {
        $ph = implode(',', array_fill(0, count($emails), '?'));
        $conds[] = 'LOWER(TRIM(fr.email_vendedor)) IN (' . $ph . ')';
        foreach ($emails as $em) {
            $params[] = $em;
        }
    }
    if (empty($conds)) {
        $fp = fingerprintVacio($feria);
        echo json_encode([
            'success' => true,
            'changed' => true,
            'fingerprint' => $fp,
            'feria' => resumenFeria($feria),
            'stats' => statsVacios(),
            'data' => [],
        ]);
        exit();
    }

    $whereVend = '(' . implode(' OR ', $conds) . ')';

    $scIdSub = 'NULL';
    if ($tieneFrScId && $tieneScFrId) {
        $scIdSub = "(SELECT scx.id FROM solicitudes_credito scx
            WHERE (fr.solicitud_credito_id IS NOT NULL AND scx.id = fr.solicitud_credito_id)
               OR (scx.financiamiento_registro_id = fr.id)
            ORDER BY scx.id DESC LIMIT 1)";
    } elseif ($tieneFrScId) {
        $scIdSub = 'fr.solicitud_credito_id';
    } elseif ($tieneScFrId) {
        $scIdSub = '(SELECT scx.id FROM solicitudes_credito scx WHERE scx.financiamiento_registro_id = fr.id ORDER BY scx.id DESC LIMIT 1)';
    }

    $scJoin = '';
    $scSelect = 'NULL AS sc_id, NULL AS sc_estado, NULL AS sc_nombre_cliente, NULL AS sc_telefono,
                 NULL AS gestor_nombre, NULL AS gestor_apellido, NULL AS ejecutivo_ventas_id_sc,
                 NULL AS veh_unidad, NULL AS veh_marca, NULL AS veh_modelo, NULL AS veh_anio,
                 0 AS tiene_reserva_aplicada';
    if ($scIdSub !== 'NULL') {
        $vehCols = solicitud_sql_campos_vehiculo_reserva('sc_fr');
        $scSelect = "
            sc_fr.id AS sc_id,
            sc_fr.estado AS sc_estado,
            sc_fr.nombre_cliente AS sc_nombre_cliente,
            sc_fr.telefono AS sc_telefono,
            ug.nombre AS gestor_nombre,
            ug.apellido AS gestor_apellido,
            sc_fr.ejecutivo_ventas_id AS ejecutivo_ventas_id_sc,
            {$vehCols}
        ";
        $scJoin = "LEFT JOIN solicitudes_credito sc_fr ON sc_fr.id = {$scIdSub}
                   LEFT JOIN usuarios ug ON ug.id = sc_fr.gestor_id";
    }

    $frCols = 'fr.id AS fr_id, fr.fecha_creacion AS fr_fecha,
               fr.cliente_nombre AS fr_cliente_nombre,
               fr.celular_cliente AS fr_celular';
    if ($tieneEmailVendedor) {
        $frCols .= ', fr.email_vendedor';
    } else {
        $frCols .= ', NULL AS email_vendedor';
    }
    if ($tieneIdVendedor) {
        $frCols .= ', fr.id_vendedor';
    } else {
        $frCols .= ', NULL AS id_vendedor';
    }
    if (columnaExiste($pdo, 'financiamiento_registros', 'marca_auto')) {
        $frCols .= ', fr.marca_auto, fr.modelo_auto, fr.anio_auto';
    } else {
        $frCols .= ', NULL AS marca_auto, NULL AS modelo_auto, NULL AS anio_auto';
    }

    $sql = "
        SELECT {$frCols}, {$scSelect}
        FROM financiamiento_registros fr
        {$scJoin}
        WHERE fr.fecha_creacion >= ? AND fr.fecha_creacion <= ?
          AND {$whereVend}
        ORDER BY fr.fecha_creacion DESC, fr.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fingerprint: count + max fr id + concat sc estados
    $fpParts = [(string) count($rawRows)];
    $maxFr = 0;
    $estadoSig = [];
    foreach ($rawRows as $r) {
        $fid = (int) ($r['fr_id'] ?? 0);
        if ($fid > $maxFr) {
            $maxFr = $fid;
        }
        $estadoSig[] = ($r['sc_id'] ?? '0') . ':' . ($r['sc_estado'] ?? '-');
    }
    $fpParts[] = (string) $maxFr;
    $fpParts[] = md5(implode('|', $estadoSig));
    $fingerprint = implode('_', $fpParts);

    if ($since !== '' && hash_equals($since, $fingerprint)) {
        echo json_encode(['success' => true, 'changed' => false, 'fingerprint' => $fingerprint]);
        exit();
    }

    // Bancos y evaluaciones para SCs
    $scIds = [];
    foreach ($rawRows as $r) {
        if (!empty($r['sc_id'])) {
            $scIds[] = (int) $r['sc_id'];
        }
    }
    $scIds = array_values(array_unique($scIds));
    $bancosPorSc = [];
    $respPorSc = [];
    if (!empty($scIds)) {
        $ph = implode(',', array_fill(0, count($scIds), '?'));
        try {
            $stmtB = $pdo->prepare("
                SELECT ubs.solicitud_id,
                       COALESCE(b.nombre, CONCAT(TRIM(u.nombre), ' ', TRIM(u.apellido))) AS banco_label
                FROM usuarios_banco_solicitudes ubs
                INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
                LEFT JOIN bancos b ON b.id = u.banco_id
                WHERE ubs.solicitud_id IN ($ph) AND ubs.estado = 'activo'
            ");
            $stmtB->execute($scIds);
            foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $sid = (int) $b['solicitud_id'];
                if (!isset($bancosPorSc[$sid])) {
                    $bancosPorSc[$sid] = [];
                }
                $label = trim((string) $b['banco_label']);
                if ($label !== '' && !in_array($label, $bancosPorSc[$sid], true)) {
                    $bancosPorSc[$sid][] = $label;
                }
            }
        } catch (PDOException $e) {
            error_log('feria_panel bancos: ' . $e->getMessage());
        }
        try {
            $stmtR = $pdo->prepare("
                SELECT eb.solicitud_id, eb.decision, b.nombre AS banco_nombre,
                       CONCAT(TRIM(u.nombre), ' ', TRIM(u.apellido)) AS usuario_banco
                FROM evaluaciones_banco eb
                LEFT JOIN usuarios_banco_solicitudes ubs ON ubs.id = eb.usuario_banco_id
                LEFT JOIN usuarios u ON u.id = ubs.usuario_banco_id
                LEFT JOIN bancos b ON b.id = u.banco_id
                WHERE eb.solicitud_id IN ($ph)
                ORDER BY eb.fecha_evaluacion DESC
            ");
            $stmtR->execute($scIds);
            foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $ev) {
                $sid = (int) $ev['solicitud_id'];
                if (!isset($respPorSc[$sid])) {
                    $respPorSc[$sid] = [];
                }
                $banco = trim((string) ($ev['banco_nombre'] ?? $ev['usuario_banco'] ?? 'Banco'));
                $dec = trim((string) ($ev['decision'] ?? ''));
                $respPorSc[$sid][] = $banco . ($dec !== '' ? ': ' . $dec : '');
            }
        } catch (PDOException $e) {
            error_log('feria_panel respuestas: ' . $e->getMessage());
        }
    }

    $rows = [];
    $stats = [
        'total' => 0,
        'sin_solicitud' => 0,
        'con_solicitud' => 0,
        'en_revision_banco' => 0,
        'aprobadas' => 0,
        'completadas' => 0,
        'rechazadas' => 0,
        'nuevas' => 0,
    ];

    foreach ($rawRows as $r) {
        $scId = !empty($r['sc_id']) ? (int) $r['sc_id'] : null;
        $tieneSc = $scId !== null && $scId > 0;

        $cliente = $tieneSc
            ? trim((string) ($r['sc_nombre_cliente'] ?? ''))
            : trim((string) ($r['fr_cliente_nombre'] ?? ''));
        if ($cliente === '') {
            $cliente = trim((string) ($r['fr_cliente_nombre'] ?? '')) ?: '—';
        }
        $telefono = $tieneSc
            ? trim((string) ($r['sc_telefono'] ?? ''))
            : trim((string) ($r['fr_celular'] ?? ''));
        if ($telefono === '') {
            $telefono = trim((string) ($r['fr_celular'] ?? ''));
        }

        $vehiculo = '—';
        if ($tieneSc) {
            $texto = solicitud_texto_vehiculo_lista($r);
            if ($texto !== '') {
                $vehiculo = $texto;
            }
        } else {
            $marca = trim((string) ($r['marca_auto'] ?? ''));
            $modelo = trim((string) ($r['modelo_auto'] ?? ''));
            $anio = trim((string) ($r['anio_auto'] ?? ''));
            $desc = trim($marca . ' ' . $modelo . ' ' . $anio);
            if ($desc !== '') {
                $vehiculo = $desc;
            }
        }

        $vendedor = '—';
        $idVend = isset($r['id_vendedor']) ? (int) $r['id_vendedor'] : 0;
        $emailVend = strtolower(trim((string) ($r['email_vendedor'] ?? '')));
        if ($idVend > 0 && isset($evById[$idVend])) {
            $vendedor = trim((string) $evById[$idVend]['nombre']);
        } elseif ($emailVend !== '' && isset($evByEmail[$emailVend])) {
            $vendedor = trim((string) $evByEmail[$emailVend]['nombre']);
        } elseif ($emailVend !== '') {
            $vendedor = $emailVend;
        }

        $gestor = '—';
        if ($tieneSc) {
            $gn = trim((string) ($r['gestor_nombre'] ?? '') . ' ' . (string) ($r['gestor_apellido'] ?? ''));
            if ($gn !== '') {
                $gestor = $gn;
            }
        }

        $bancos = $tieneSc ? ($bancosPorSc[$scId] ?? []) : [];
        $respuestas = $tieneSc ? ($respPorSc[$scId] ?? []) : [];
        // Dedupe respuestas (última por banco ya viene ordered)
        $respuestas = array_values(array_unique($respuestas));

        $estado = $tieneSc
            ? (trim((string) ($r['sc_estado'] ?? '')) ?: '—')
            : 'Sin solicitud asociada';

        $stats['total']++;
        if ($tieneSc) {
            $stats['con_solicitud']++;
            switch ($estado) {
                case 'En Revisión Banco':
                case 'Reevaluación por los Bancos':
                    $stats['en_revision_banco']++;
                    break;
                case 'Aprobada':
                    $stats['aprobadas']++;
                    break;
                case 'Completada':
                    $stats['completadas']++;
                    break;
                case 'Rechazada':
                    $stats['rechazadas']++;
                    break;
                case 'Nueva':
                    $stats['nuevas']++;
                    break;
            }
        } else {
            $stats['sin_solicitud']++;
        }

        $rows[] = [
            'fr_id' => (int) $r['fr_id'],
            'sc_id' => $scId,
            'tiene_solicitud_credito' => $tieneSc,
            'cliente_nombre' => $cliente,
            'cliente_telefono' => $telefono,
            'vehiculo' => $vehiculo,
            'vendedor' => $vendedor,
            'gestor' => $gestor,
            'bancos_asignados' => $bancos,
            'respuestas_bancos' => $respuestas,
            'estado' => $estado,
            'fecha_creacion' => $r['fr_fecha'] ?? null,
        ];
    }

    echo json_encode([
        'success' => true,
        'changed' => true,
        'fingerprint' => $fingerprint,
        'feria' => resumenFeria($feria),
        'stats' => $stats,
        'data' => $rows,
    ]);
} catch (PDOException $e) {
    error_log('feria_panel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos. ¿Ejecutó migracion_ferias.sql?']);
}

function columnaExiste(PDO $pdo, string $tabla, string $columna): bool
{
    static $cache = [];
    $key = $tabla . '.' . $columna;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$tabla, $columna]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function resumenFeria(array $feria): array
{
    return [
        'id' => (int) $feria['id'],
        'nombre' => $feria['nombre'],
        'fecha_inicio' => $feria['fecha_inicio'],
        'fecha_fin' => $feria['fecha_fin'],
        'lugar' => $feria['lugar'] ?? '',
        'descripcion' => $feria['descripcion'] ?? '',
    ];
}

function statsVacios(): array
{
    return [
        'total' => 0,
        'sin_solicitud' => 0,
        'con_solicitud' => 0,
        'en_revision_banco' => 0,
        'aprobadas' => 0,
        'completadas' => 0,
        'rechazadas' => 0,
        'nuevas' => 0,
    ];
}

function fingerprintVacio(array $feria): string
{
    return '0_0_' . md5((string) ($feria['fecha_actualizacion'] ?? $feria['id']));
}
