<?php
/**
 * Motor de predicciones MVP (estadístico + heurístico, sin ML).
 * Bloques: estancamiento, probabilidad de cierre, SLA esperado por banco.
 */

const REP_PRED_ESTADOS_TERMINALES = ['Completada', 'Rechazada', 'Desistimiento'];

const REP_PRED_ESTADOS_RIESGO = [
    'Nueva' => 15,
    'En Revisión Banco' => 25,
    'Evaluacion' => 20,
    'Comité' => 30,
    'Reconsideración' => 35,
    'Pre Aprobado' => 18,
    'Aprobado con Condición' => 22,
    'Reevaluación por los Bancos' => 28,
    'Aprobada' => 12,
    'Pend. Firma' => 40,
    'Pend. Poliza' => 42,
    'Pend. Abono' => 40,
    'Pend. Abono y poliza' => 45,
    'Pend. CPP' => 38,
];

function rep_pred_tabla_existe(PDO $pdo, string $tabla): bool
{
    static $cache = [];
    if (array_key_exists($tabla, $cache)) {
        return $cache[$tabla];
    }
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$tabla]);
    $cache[$tabla] = (int) $st->fetchColumn() > 0;

    return $cache[$tabla];
}

function rep_pred_columna_existe(PDO $pdo, string $tabla, string $col): bool
{
    static $cache = [];
    $key = $tabla . '.' . $col;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$tabla, $col]);
    $cache[$key] = (int) $st->fetchColumn() > 0;

    return $cache[$key];
}

function rep_pred_bucket_abono(?float $pct): string
{
    if ($pct === null || $pct < 0) {
        return 'sin_dato';
    }
    if ($pct < 10) {
        return '0-9';
    }
    if ($pct < 20) {
        return '10-19';
    }
    if ($pct < 30) {
        return '20-29';
    }
    if ($pct < 40) {
        return '30-39';
    }

    return '40+';
}

function rep_pred_codigo_sucursal(?string $sucursal): string
{
    $s = strtoupper(trim((string) $sucursal));
    if ($s === '') {
        return 'NN';
    }
    if (str_starts_with($s, 'SP-')) {
        $s = substr($s, 3);
    }
    $s = preg_replace('/[^A-Z0-9]/', '', $s) ?: 'NN';

    return $s !== '' ? $s : 'NN';
}

function rep_pred_nivel_riesgo(int $score): string
{
    if ($score >= 70) {
        return 'alto';
    }
    if ($score >= 40) {
        return 'medio';
    }

    return 'bajo';
}

/**
 * Medianas de horas de respuesta por banco (últimos N días).
 *
 * @return array{bancos: list<array>, pendiente_alerta: list<array>, meta: array}
 */
function rep_pred_sla_bancos(PDO $pdo, int $diasHistorico = 90): array
{
    $sql = "
        SELECT
            b.id AS banco_id,
            b.nombre AS banco_nombre,
            ubs.solicitud_id,
            ubs.fecha_asignacion,
            MIN(eb.fecha_evaluacion) AS fecha_respuesta,
            TIMESTAMPDIFF(HOUR, ubs.fecha_asignacion, MIN(eb.fecha_evaluacion)) AS horas_respuesta
        FROM usuarios_banco_solicitudes ubs
        INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
        INNER JOIN bancos b ON b.id = u.banco_id
        LEFT JOIN evaluaciones_banco eb
            ON eb.solicitud_id = ubs.solicitud_id
           AND eb.usuario_banco_id = ubs.id
        WHERE ubs.fecha_asignacion >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY b.id, b.nombre, ubs.id, ubs.solicitud_id, ubs.fecha_asignacion
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$diasHistorico]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $horasPorBanco = [];
    $pendientes = [];
    foreach ($rows as $r) {
        $bid = (int) ($r['banco_id'] ?? 0);
        if ($bid <= 0) {
            continue;
        }
        if (!isset($horasPorBanco[$bid])) {
            $horasPorBanco[$bid] = [
                'banco_id' => $bid,
                'banco_nombre' => (string) ($r['banco_nombre'] ?? 'Banco'),
                'horas' => [],
                'respondidas' => 0,
                'pendientes' => 0,
            ];
        }
        if ($r['fecha_respuesta'] !== null && $r['horas_respuesta'] !== null && (int) $r['horas_respuesta'] >= 0) {
            $horasPorBanco[$bid]['horas'][] = (int) $r['horas_respuesta'];
            $horasPorBanco[$bid]['respondidas']++;
        } else {
            $horasPorBanco[$bid]['pendientes']++;
            $pendientes[] = [
                'banco_id' => $bid,
                'banco_nombre' => (string) ($r['banco_nombre'] ?? 'Banco'),
                'solicitud_id' => (int) ($r['solicitud_id'] ?? 0),
                'fecha_asignacion' => (string) ($r['fecha_asignacion'] ?? ''),
                'horas_transcurridas' => null,
            ];
        }
    }

    // Horas transcurridas de pendientes
    if ($pendientes) {
        $stH = $pdo->prepare('SELECT TIMESTAMPDIFF(HOUR, ?, NOW())');
        foreach ($pendientes as &$p) {
            if ($p['fecha_asignacion'] === '') {
                continue;
            }
            $stH->execute([$p['fecha_asignacion']]);
            $p['horas_transcurridas'] = (int) $stH->fetchColumn();
        }
        unset($p);
    }

    $bancos = [];
    foreach ($horasPorBanco as $b) {
        $arr = $b['horas'];
        sort($arr);
        $n = count($arr);
        $mediana = null;
        $promedio = null;
        if ($n > 0) {
            $mid = intdiv($n, 2);
            $mediana = $n % 2 === 1 ? $arr[$mid] : (int) round(($arr[$mid - 1] + $arr[$mid]) / 2);
            $promedio = (int) round(array_sum($arr) / $n);
        }
        $bancos[] = [
            'banco_id' => $b['banco_id'],
            'banco_nombre' => $b['banco_nombre'],
            'muestra' => $n,
            'respondidas' => $b['respondidas'],
            'pendientes' => $b['pendientes'],
            'horas_mediana' => $mediana,
            'horas_promedio' => $promedio,
            'dias_mediana' => $mediana !== null ? round($mediana / 24, 1) : null,
        ];
    }

    usort($bancos, static function ($a, $b) {
        $am = $a['horas_mediana'] ?? PHP_INT_MAX;
        $bm = $b['horas_mediana'] ?? PHP_INT_MAX;

        return $am <=> $bm;
    });

    $medianaById = [];
    foreach ($bancos as $b) {
        $medianaById[(int) $b['banco_id']] = $b['horas_mediana'];
    }

    $alertas = [];
    foreach ($pendientes as $p) {
        $med = $medianaById[(int) $p['banco_id']] ?? null;
        $horas = $p['horas_transcurridas'];
        if ($horas === null) {
            continue;
        }
        $ratio = ($med !== null && $med > 0) ? round($horas / $med, 2) : null;
        $alerta = ($med !== null && $horas >= $med) || ($med === null && $horas >= 48);
        if (!$alerta) {
            continue;
        }
        $alertas[] = [
            'solicitud_id' => $p['solicitud_id'],
            'banco_id' => $p['banco_id'],
            'banco_nombre' => $p['banco_nombre'],
            'fecha_asignacion' => $p['fecha_asignacion'],
            'horas_transcurridas' => $horas,
            'horas_esperadas_mediana' => $med,
            'ratio_vs_mediana' => $ratio,
            'nivel' => ($ratio !== null && $ratio >= 1.5) || ($med === null && $horas >= 72) ? 'alto' : 'medio',
        ];
    }

    usort($alertas, static fn($a, $b) => ($b['horas_transcurridas'] ?? 0) <=> ($a['horas_transcurridas'] ?? 0));

    return [
        'bancos' => $bancos,
        'pendiente_alerta' => array_slice($alertas, 0, 50),
        'meta' => [
            'dias_historico' => $diasHistorico,
            'total_bancos' => count($bancos),
            'alertas_sla' => count($alertas),
        ],
    ];
}

/**
 * Tasas históricas de Completada por bucket (sucursal × abono × tiene_eval_positiva).
 *
 * @return array{tasas: array<string, array{total:int,completadas:int,tasa:float}>, global: float, meta: array}
 */
function rep_pred_tasas_cierre(PDO $pdo, int $diasHistorico = 365): array
{
    $term = implode(',', array_fill(0, count(REP_PRED_ESTADOS_TERMINALES), '?'));
    $hasScore = rep_pred_columna_existe($pdo, 'solicitudes_credito', 'score_apc');
    $scoreSel = $hasScore ? ', s.score_apc' : ', NULL AS score_apc';

    $sql = "
        SELECT
            s.id,
            s.estado,
            s.abono_porcentaje,
            s.ingreso,
            s.precio_especial,
            ev.sucursal
            {$scoreSel},
            EXISTS (
                SELECT 1 FROM evaluaciones_banco eb
                WHERE eb.solicitud_id = s.id
                  AND eb.decision IN ('aprobado', 'preaprobado', 'aprobado_condicional')
            ) AS tiene_eval_positiva
        FROM solicitudes_credito s
        LEFT JOIN ejecutivos_ventas ev ON ev.id = s.ejecutivo_ventas_id
        WHERE s.estado IN ({$term})
          AND s.fecha_creacion >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ";
    $params = array_merge(REP_PRED_ESTADOS_TERMINALES, [$diasHistorico]);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $buckets = [];
    $completadas = 0;
    $total = 0;
    foreach ($rows as $r) {
        $total++;
        $ok = (($r['estado'] ?? '') === 'Completada');
        if ($ok) {
            $completadas++;
        }
        $suc = rep_pred_codigo_sucursal($r['sucursal'] ?? null);
        $ab = rep_pred_bucket_abono(
            isset($r['abono_porcentaje']) && is_numeric($r['abono_porcentaje'])
                ? (float) $r['abono_porcentaje']
                : null
        );
        $eval = ((int) ($r['tiene_eval_positiva'] ?? 0)) === 1 ? '1' : '0';
        $key = $suc . '|' . $ab . '|' . $eval;
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['total' => 0, 'completadas' => 0, 'tasa' => 0.0];
        }
        $buckets[$key]['total']++;
        if ($ok) {
            $buckets[$key]['completadas']++;
        }
    }
    foreach ($buckets as $k => $b) {
        $buckets[$k]['tasa'] = $b['total'] > 0 ? round($b['completadas'] / $b['total'], 4) : 0.0;
    }

    $global = $total > 0 ? round($completadas / $total, 4) : 0.35;

    return [
        'tasas' => $buckets,
        'global' => $global,
        'meta' => [
            'dias_historico' => $diasHistorico,
            'muestra_cerradas' => $total,
            'completadas' => $completadas,
        ],
    ];
}

function rep_pred_tasa_para_solicitud(array $tasasInfo, string $sucursalCod, string $abonoBucket, bool $tieneEvalPos): float
{
    $tasas = $tasasInfo['tasas'] ?? [];
    $global = (float) ($tasasInfo['global'] ?? 0.35);
    $eval = $tieneEvalPos ? '1' : '0';
    $candidates = [
        $sucursalCod . '|' . $abonoBucket . '|' . $eval,
        $sucursalCod . '|sin_dato|' . $eval,
        'NN|' . $abonoBucket . '|' . $eval,
        $sucursalCod . '|' . $abonoBucket . '|0',
    ];
    foreach ($candidates as $key) {
        if (isset($tasas[$key]) && ($tasas[$key]['total'] ?? 0) >= 5) {
            return (float) $tasas[$key]['tasa'];
        }
    }
    // Fallback: promedio ponderado de buckets con misma eval
    $sum = 0;
    $n = 0;
    foreach ($tasas as $key => $b) {
        if (str_ends_with($key, '|' . $eval) && ($b['total'] ?? 0) >= 3) {
            $sum += $b['tasa'] * $b['total'];
            $n += $b['total'];
        }
    }
    if ($n > 0) {
        return round($sum / $n, 4);
    }

    return $global;
}

/**
 * Score de estancamiento 0–100 para solicitudes abiertas.
 *
 * @return list<array>
 */
function rep_pred_estancamiento_abiertas(PDO $pdo, int $limit = 100): array
{
    $term = implode(',', array_map(static fn($e) => $pdo->quote($e), REP_PRED_ESTADOS_TERMINALES));
    $hasHistorial = rep_pred_tabla_existe($pdo, 'historial_solicitud');
    $hasAdjuntos = rep_pred_tabla_existe($pdo, 'adjuntos_solicitud');
    $hasCitas = rep_pred_tabla_existe($pdo, 'citas_firma');

    $ultimoHist = $hasHistorial
        ? '(SELECT MAX(h.fecha_creacion) FROM historial_solicitud h WHERE h.solicitud_id = s.id)'
        : 'NULL';
    $ultAdj = $hasAdjuntos
        ? '(SELECT MAX(a.fecha_subida) FROM adjuntos_solicitud a WHERE a.solicitud_id = s.id)'
        : 'NULL';
    $tieneCita = $hasCitas
        ? 'EXISTS (SELECT 1 FROM citas_firma cf WHERE cf.solicitud_id = s.id)'
        : '0';

    $sql = "
        SELECT
            s.id,
            s.nombre_cliente,
            s.cedula,
            s.estado,
            s.fecha_creacion,
            s.fecha_actualizacion,
            s.abono_porcentaje,
            s.ingreso,
            s.precio_especial,
            s.gestor_id,
            u.nombre AS gestor_nombre,
            ev.nombre AS ejecutivo_nombre,
            ev.sucursal,
            {$ultimoHist} AS ultimo_historial,
            {$ultAdj} AS ultimo_adjunto,
            {$tieneCita} AS tiene_cita_firma,
            EXISTS (
                SELECT 1 FROM evaluaciones_banco eb WHERE eb.solicitud_id = s.id
            ) AS tiene_evaluacion,
            EXISTS (
                SELECT 1 FROM evaluaciones_banco eb
                WHERE eb.solicitud_id = s.id
                  AND eb.decision IN ('aprobado', 'preaprobado', 'aprobado_condicional')
            ) AS tiene_eval_positiva,
            TIMESTAMPDIFF(HOUR, COALESCE({$ultimoHist}, s.fecha_actualizacion, s.fecha_creacion), NOW()) AS horas_sin_avance,
            TIMESTAMPDIFF(DAY, s.fecha_creacion, NOW()) AS dias_abierta
        FROM solicitudes_credito s
        LEFT JOIN usuarios u ON u.id = s.gestor_id
        LEFT JOIN ejecutivos_ventas ev ON ev.id = s.ejecutivo_ventas_id
        WHERE s.estado NOT IN ({$term})
        ORDER BY horas_sin_avance DESC
        LIMIT " . (int) $limit;

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $horas = (int) ($r['horas_sin_avance'] ?? 0);
        $estado = (string) ($r['estado'] ?? '');
        $score = (int) (REP_PRED_ESTADOS_RIESGO[$estado] ?? 20);

        // Inactividad
        if ($horas >= 168) { // 7 días
            $score += 40;
        } elseif ($horas >= 96) {
            $score += 30;
        } elseif ($horas >= 72) {
            $score += 22;
        } elseif ($horas >= 48) {
            $score += 14;
        } elseif ($horas >= 24) {
            $score += 8;
        }

        if (!(int) ($r['tiene_evaluacion'] ?? 0) && in_array($estado, ['En Revisión Banco', 'Evaluacion', 'Comité'], true)) {
            $score += 12;
        }
        if (!(int) ($r['tiene_cita_firma'] ?? 0) && str_starts_with($estado, 'Pend.')) {
            $score += 10;
        }
        if (empty($r['ultimo_adjunto']) && $horas >= 72) {
            $score += 6;
        }
        if ((int) ($r['dias_abierta'] ?? 0) >= 30) {
            $score += 8;
        }

        $score = max(0, min(100, $score));
        $factores = [];
        if ($horas >= 168) {
            $factores[] = 'Sin avance > 7 días';
        } elseif ($horas >= 72) {
            $factores[] = 'Sin avance > 72 h';
        }
        if (!(int) ($r['tiene_evaluacion'] ?? 0)) {
            $factores[] = 'Sin evaluación bancaria';
        }
        if (str_starts_with($estado, 'Pend.')) {
            $factores[] = 'Estado pendiente de cierre';
        }
        if ((int) ($r['dias_abierta'] ?? 0) >= 30) {
            $factores[] = 'Abierta > 30 días';
        }

        $out[] = [
            'solicitud_id' => (int) $r['id'],
            'nombre_cliente' => (string) ($r['nombre_cliente'] ?? ''),
            'cedula' => (string) ($r['cedula'] ?? ''),
            'estado' => $estado,
            'sucursal' => rep_pred_codigo_sucursal($r['sucursal'] ?? null),
            'gestor_nombre' => (string) ($r['gestor_nombre'] ?? ''),
            'ejecutivo_nombre' => (string) ($r['ejecutivo_nombre'] ?? ''),
            'horas_sin_avance' => $horas,
            'dias_abierta' => (int) ($r['dias_abierta'] ?? 0),
            'score_estancamiento' => $score,
            'nivel' => rep_pred_nivel_riesgo($score),
            'factores' => $factores,
            'tiene_evaluacion' => (int) ($r['tiene_evaluacion'] ?? 0) === 1,
            'tiene_eval_positiva' => (int) ($r['tiene_eval_positiva'] ?? 0) === 1,
            'abono_porcentaje' => isset($r['abono_porcentaje']) && is_numeric($r['abono_porcentaje'])
                ? (float) $r['abono_porcentaje'] : null,
            'fecha_creacion' => (string) ($r['fecha_creacion'] ?? ''),
            'ultimo_historial' => $r['ultimo_historial'] ?? null,
        ];
    }

    usort($out, static fn($a, $b) => $b['score_estancamiento'] <=> $a['score_estancamiento']);

    return $out;
}

/**
 * Probabilidad de cierre para solicitudes abiertas (usando tasas históricas).
 *
 * @return list<array>
 */
function rep_pred_prob_cierre_abiertas(PDO $pdo, array $tasasInfo, array $estancadas): array
{
    $out = [];
    foreach ($estancadas as $r) {
        $abBucket = rep_pred_bucket_abono($r['abono_porcentaje'] ?? null);
        $tasa = rep_pred_tasa_para_solicitud(
            $tasasInfo,
            (string) ($r['sucursal'] ?? 'NN'),
            $abBucket,
            !empty($r['tiene_eval_positiva'])
        );

        // Ajuste suave por riesgo de estancamiento (no anula la tasa histórica)
        $scoreEst = (int) ($r['score_estancamiento'] ?? 0);
        $ajuste = 1.0 - min(0.35, $scoreEst / 280);
        $prob = max(0.02, min(0.95, round($tasa * $ajuste, 4)));

        $out[] = [
            'solicitud_id' => $r['solicitud_id'],
            'nombre_cliente' => $r['nombre_cliente'],
            'cedula' => $r['cedula'],
            'estado' => $r['estado'],
            'sucursal' => $r['sucursal'],
            'gestor_nombre' => $r['gestor_nombre'],
            'ejecutivo_nombre' => $r['ejecutivo_nombre'],
            'prob_cierre' => $prob,
            'prob_cierre_pct' => (int) round($prob * 100),
            'tasa_bucket' => $tasa,
            'abono_bucket' => $abBucket,
            'score_estancamiento' => $scoreEst,
            'nivel_estancamiento' => $r['nivel'],
            'tiene_eval_positiva' => !empty($r['tiene_eval_positiva']),
            'dias_abierta' => $r['dias_abierta'],
        ];
    }

    usort($out, static fn($a, $b) => $b['prob_cierre'] <=> $a['prob_cierre']);

    return $out;
}

/**
 * Payload completo del reporte de predicciones.
 *
 * @return array{success:bool, kpis:array, estancamiento:list, probabilidad_cierre:list, sla_bancos:array, meta:array}
 */
function rep_pred_build_reporte(PDO $pdo): array
{
    $estancamiento = rep_pred_estancamiento_abiertas($pdo, 150);
    $tasas = rep_pred_tasas_cierre($pdo, 365);
    $probCierre = rep_pred_prob_cierre_abiertas($pdo, $tasas, $estancamiento);
    $sla = rep_pred_sla_bancos($pdo, 90);

    $alto = 0;
    $medio = 0;
    $bajo = 0;
    foreach ($estancamiento as $e) {
        if ($e['nivel'] === 'alto') {
            $alto++;
        } elseif ($e['nivel'] === 'medio') {
            $medio++;
        } else {
            $bajo++;
        }
    }

    $probMedia = 0.0;
    $nProb = count($probCierre);
    if ($nProb > 0) {
        $probMedia = round(array_sum(array_column($probCierre, 'prob_cierre')) / $nProb, 4);
    }

    // Ordenar estancamiento: alto primero (ya ordenado por score)
    $topRiesgo = array_values(array_filter($estancamiento, static fn($e) => $e['nivel'] !== 'bajo'));

    return [
        'success' => true,
        'kpis' => [
            'abiertas_analizadas' => count($estancamiento),
            'riesgo_alto' => $alto,
            'riesgo_medio' => $medio,
            'riesgo_bajo' => $bajo,
            'prob_cierre_promedio_pct' => (int) round($probMedia * 100),
            'tasa_cierre_historica_pct' => (int) round(((float) $tasas['global']) * 100),
            'muestra_historica_cerradas' => (int) ($tasas['meta']['muestra_cerradas'] ?? 0),
            'alertas_sla_banco' => (int) ($sla['meta']['alertas_sla'] ?? 0),
            'bancos_con_sla' => (int) ($sla['meta']['total_bancos'] ?? 0),
        ],
        'estancamiento' => $estancamiento,
        'estancamiento_top' => array_slice($topRiesgo ?: $estancamiento, 0, 40),
        'probabilidad_cierre' => $probCierre,
        'sla_bancos' => $sla,
        'meta' => [
            'metodo' => 'heuristico_estadistico',
            'nota' => 'Scores basados en inactividad, estado, evaluaciones y tasas históricas por sucursal/abono. No es un modelo de machine learning.',
            'generado_en' => date('c'),
        ],
    ];
}
