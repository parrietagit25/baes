<?php
/**
 * Seguimiento para admin banco: asignadas vs respuestas vs seleccionadas (alcance entidad).
 */

declare(strict_types=1);

require_once __DIR__ . '/reportes_fin_demografia_data.php';

/**
 * @return array{desde:string,hasta:string}
 */
function rep_segbanco_parse_filtros(): array
{
    $desde = isset($_GET['desde']) ? trim((string) $_GET['desde']) : '';
    $hasta = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : '';
    foreach (['desde' => &$desde, 'hasta' => &$hasta] as $_k => &$_v) {
        if ($_v !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $_v, $m)) {
            $_v = $m[1];
        }
    }
    unset($_v);

    return ['desde' => $desde, 'hasta' => $hasta];
}

/**
 * @return array<int,array<string,mixed>>
 */
function rep_segbanco_fetch_raw(PDO $pdo, int $bancoId, string $d1, string $d2): array
{
    $hasRazon = rep_fin_columna_existe($pdo, 'evaluaciones_banco', 'razon');
    $hasCuantia = rep_fin_columna_existe($pdo, 'evaluaciones_banco', 'cuantia');
    $hasLetraQ = rep_fin_columna_existe($pdo, 'evaluaciones_banco', 'letra_quincenal');
    $hasVeh = rep_fin_tabla_existe($pdo, 'vehiculos_solicitud');

    $sqlRazon = $hasRazon ? 'eb_sel.razon' : 'NULL';
    $sqlCuantia = $hasCuantia ? 'eb_sel.cuantia' : 'NULL';
    $sqlLetraQ = $hasLetraQ ? 'eb_sel.letra_quincenal' : 'NULL';
    $vehCols = $hasVeh
        ? '(SELECT v.marca FROM vehiculos_solicitud v WHERE v.solicitud_id = sc.id ORDER BY v.id ASC LIMIT 1) AS vehiculo_marca,
           (SELECT v.modelo FROM vehiculos_solicitud v WHERE v.solicitud_id = sc.id ORDER BY v.id ASC LIMIT 1) AS vehiculo_modelo,
           (SELECT v.anio FROM vehiculos_solicitud v WHERE v.solicitud_id = sc.id ORDER BY v.id ASC LIMIT 1) AS vehiculo_anio'
        : 'NULL AS vehiculo_marca, NULL AS vehiculo_modelo, NULL AS vehiculo_anio';

    $sql = "
        SELECT
            sc.id AS solicitud_id,
            sc.nombre_cliente,
            sc.cedula,
            sc.estado AS solicitud_estado,
            sc.fecha_creacion,
            sc.fecha_actualizacion,
            sc.evaluacion_seleccionada,
            (
                SELECT MIN(ubs2.fecha_asignacion)
                FROM usuarios_banco_solicitudes ubs2
                INNER JOIN usuarios u2 ON u2.id = ubs2.usuario_banco_id
                WHERE ubs2.solicitud_id = sc.id
                  AND ubs2.estado = 'activo'
                  AND u2.banco_id = :banco_id
            ) AS fecha_asignacion,
            (
                SELECT GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(u3.nombre, ''), ' ', COALESCE(u3.apellido, '')))
                    ORDER BY u3.apellido, u3.nombre SEPARATOR ', ')
                FROM usuarios_banco_solicitudes ubs3
                INNER JOIN usuarios u3 ON u3.id = ubs3.usuario_banco_id
                WHERE ubs3.solicitud_id = sc.id
                  AND ubs3.estado = 'activo'
                  AND u3.banco_id = :banco_id2
            ) AS encargados,
            (
                SELECT COUNT(*)
                FROM usuarios_banco_solicitudes ubs4
                INNER JOIN usuarios u4 ON u4.id = ubs4.usuario_banco_id
                WHERE ubs4.solicitud_id = sc.id
                  AND ubs4.estado = 'activo'
                  AND u4.banco_id = :banco_id3
            ) AS total_asignaciones,
            (
                SELECT COUNT(*)
                FROM evaluaciones_banco eb2
                INNER JOIN usuarios_banco_solicitudes ubs5 ON ubs5.id = eb2.usuario_banco_id
                INNER JOIN usuarios u5 ON u5.id = ubs5.usuario_banco_id
                WHERE eb2.solicitud_id = sc.id
                  AND u5.banco_id = :banco_id4
            ) AS total_respuestas,
            (
                SELECT eb3.decision
                FROM evaluaciones_banco eb3
                INNER JOIN usuarios_banco_solicitudes ubs6 ON ubs6.id = eb3.usuario_banco_id
                INNER JOIN usuarios u6 ON u6.id = ubs6.usuario_banco_id
                WHERE eb3.solicitud_id = sc.id
                  AND u6.banco_id = :banco_id5
                ORDER BY eb3.fecha_evaluacion DESC
                LIMIT 1
            ) AS ultima_decision,
            (
                SELECT TRIM(CONCAT(COALESCE(u7.nombre, ''), ' ', COALESCE(u7.apellido, '')))
                FROM evaluaciones_banco eb4
                INNER JOIN usuarios_banco_solicitudes ubs7 ON ubs7.id = eb4.usuario_banco_id
                INNER JOIN usuarios u7 ON u7.id = ubs7.usuario_banco_id
                WHERE eb4.solicitud_id = sc.id
                  AND u7.banco_id = :banco_id6
                ORDER BY eb4.fecha_evaluacion DESC
                LIMIT 1
            ) AS ultimo_analista,
            (
                SELECT eb5.fecha_evaluacion
                FROM evaluaciones_banco eb5
                INNER JOIN usuarios_banco_solicitudes ubs8 ON ubs8.id = eb5.usuario_banco_id
                INNER JOIN usuarios u8 ON u8.id = ubs8.usuario_banco_id
                WHERE eb5.solicitud_id = sc.id
                  AND u8.banco_id = :banco_id7
                ORDER BY eb5.fecha_evaluacion DESC
                LIMIT 1
            ) AS fecha_ultima_respuesta,
            CASE
                WHEN sc.evaluacion_seleccionada IS NOT NULL
                 AND EXISTS (
                    SELECT 1
                    FROM evaluaciones_banco eb_chk
                    INNER JOIN usuarios_banco_solicitudes ubs_chk ON ubs_chk.id = eb_chk.usuario_banco_id
                    INNER JOIN usuarios u_chk ON u_chk.id = ubs_chk.usuario_banco_id
                    WHERE eb_chk.id = sc.evaluacion_seleccionada
                      AND u_chk.banco_id = :banco_id8
                 ) THEN 1
                ELSE 0
            END AS es_seleccionada_banco,
            eb_sel.decision AS decision_seleccionada,
            {$sqlRazon} AS razon_seleccionada,
            eb_sel.tasa_bancaria AS tasa_seleccionada,
            eb_sel.valor_financiar AS valor_financiar_seleccionada,
            eb_sel.abono AS abono_seleccionada,
            eb_sel.plazo AS plazo_seleccionada,
            eb_sel.letra AS letra_seleccionada,
            {$sqlLetraQ} AS letra_quincenal_seleccionada,
            eb_sel.promocion AS promocion_seleccionada,
            {$sqlCuantia} AS cuantia_seleccionada,
            eb_sel.comentarios AS comentarios_seleccionada,
            eb_sel.fecha_evaluacion AS fecha_eval_seleccionada,
            TRIM(CONCAT(COALESCE(u_sel.nombre, ''), ' ', COALESCE(u_sel.apellido, ''))) AS encargado_seleccionada,
            {$vehCols}
        FROM solicitudes_credito sc
        LEFT JOIN evaluaciones_banco eb_sel ON eb_sel.id = sc.evaluacion_seleccionada
        LEFT JOIN usuarios_banco_solicitudes ubs_sel ON ubs_sel.id = eb_sel.usuario_banco_id
        LEFT JOIN usuarios u_sel ON u_sel.id = ubs_sel.usuario_banco_id
        WHERE EXISTS (
            SELECT 1
            FROM usuarios_banco_solicitudes ubs
            INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id
            WHERE ubs.solicitud_id = sc.id
              AND ubs.estado = 'activo'
              AND u.banco_id = :banco_id9
              AND (
                (ubs.fecha_asignacion IS NOT NULL AND DATE(ubs.fecha_asignacion) BETWEEN :d1a AND :d2a)
                OR (sc.fecha_creacion IS NOT NULL AND DATE(sc.fecha_creacion) BETWEEN :d1b AND :d2b)
              )
        )
        ORDER BY fecha_asignacion DESC, sc.id DESC
        LIMIT 15000
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        'banco_id' => $bancoId,
        'banco_id2' => $bancoId,
        'banco_id3' => $bancoId,
        'banco_id4' => $bancoId,
        'banco_id5' => $bancoId,
        'banco_id6' => $bancoId,
        'banco_id7' => $bancoId,
        'banco_id8' => $bancoId,
        'banco_id9' => $bancoId,
        'd1a' => $d1,
        'd2a' => $d2,
        'd1b' => $d1,
        'd2b' => $d2,
    ]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function rep_segbanco_enriquecer(array $row): array
{
    $tieneRespuesta = ((int) ($row['total_respuestas'] ?? 0)) > 0;
    $esSeleccionada = !empty($row['es_seleccionada_banco']);
    $row['tiene_respuesta'] = $tieneRespuesta;
    $row['es_seleccionada'] = $esSeleccionada;
    $row['vehiculo_label'] = trim(implode(' ', array_filter([
        $row['vehiculo_marca'] ?? '',
        $row['vehiculo_modelo'] ?? '',
        isset($row['vehiculo_anio']) && $row['vehiculo_anio'] !== null && $row['vehiculo_anio'] !== ''
            ? (string) $row['vehiculo_anio']
            : '',
    ], static fn($v) => $v !== null && $v !== '')));

    // No exponer datos de propuesta seleccionada de otro banco.
    if (!$esSeleccionada) {
        $row['decision_seleccionada'] = null;
        $row['razon_seleccionada'] = null;
        $row['tasa_seleccionada'] = null;
        $row['valor_financiar_seleccionada'] = null;
        $row['abono_seleccionada'] = null;
        $row['plazo_seleccionada'] = null;
        $row['letra_seleccionada'] = null;
        $row['letra_quincenal_seleccionada'] = null;
        $row['promocion_seleccionada'] = null;
        $row['cuantia_seleccionada'] = null;
        $row['comentarios_seleccionada'] = null;
        $row['fecha_eval_seleccionada'] = null;
        $row['encargado_seleccionada'] = null;
    }

    return $row;
}

/**
 * @return array<string,mixed>
 */
function rep_segbanco_build_reporte(PDO $pdo, int $bancoId): array
{
    $filt = rep_segbanco_parse_filtros();
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);

    try {
        $raw = rep_segbanco_fetch_raw($pdo, $bancoId, $d1, $d2);
    } catch (PDOException $e) {
        error_log('rep_segbanco_fetch_raw: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error de base de datos'];
    }

    $filas = [];
    $asignadas = 0;
    $conRespuesta = 0;
    $seleccionadas = 0;

    foreach ($raw as $row) {
        $e = rep_segbanco_enriquecer($row);
        $asignadas++;
        if ($e['tiene_respuesta']) {
            $conRespuesta++;
        }
        if ($e['es_seleccionada']) {
            $seleccionadas++;
        }
        $filas[] = $e;
    }

    return [
        'success' => true,
        'filtros' => array_merge($filt, ['fecha_desde' => $d1, 'fecha_hasta' => $d2, 'banco_id' => $bancoId]),
        'kpis' => [
            'total_solicitudes' => $asignadas,
            'total_respuestas' => $conRespuesta,
            'total_seleccionadas' => $seleccionadas,
            'sin_respuesta' => max(0, $asignadas - $conRespuesta),
            'respuestas_no_seleccionadas' => max(0, $conRespuesta - $seleccionadas),
        ],
        'chart_asignadas_vs_enviadas' => [
            ['label' => 'Asignadas', 'total' => $asignadas],
            ['label' => 'Enviadas a evaluar', 'total' => $conRespuesta],
        ],
        'chart_enviadas_vs_seleccionadas' => [
            ['label' => 'Enviadas a evaluar', 'total' => $conRespuesta],
            ['label' => 'Seleccionadas', 'total' => $seleccionadas],
        ],
        'filas' => $filas,
        'nota' => 'Solicitudes con asignación activa a usuarios de su banco. Fechas: asignación o creación Motus en el rango.',
    ];
}

/**
 * @return array{headers:array<int,string>,rows:array<int,array<int,string|int|float|null>>}
 */
function rep_segbanco_export_pack(PDO $pdo, int $bancoId): array
{
    $rep = rep_segbanco_build_reporte($pdo, $bancoId);
    $headers = [
        'ID solicitud',
        'Fecha asignación',
        'Fecha creación Motus',
        'Cliente',
        'Cédula',
        'Estado Motus',
        'Encargado(s)',
        'Asignaciones',
        'Respuestas',
        'Tiene respuesta',
        'Última decisión',
        'Último analista',
        'Fecha última respuesta',
        'Seleccionada (banco)',
        'Decisión seleccionada',
        'Razón',
        'Tasa %',
        'Valor financiar',
        'Abono',
        'Plazo',
        'Letra mens.',
        'Letra quinc.',
        'Promoción',
        'Cuantía',
        'Comentarios',
        'Encargado seleccionada',
        'Fecha eval. seleccionada',
        'Vehículo',
    ];
    $rows = [];
    foreach ($rep['filas'] ?? [] as $r) {
        $rows[] = [
            $r['solicitud_id'] ?? '',
            $r['fecha_asignacion'] ?? '',
            $r['fecha_creacion'] ?? '',
            $r['nombre_cliente'] ?? '',
            $r['cedula'] ?? '',
            $r['solicitud_estado'] ?? '',
            $r['encargados'] ?? '',
            $r['total_asignaciones'] ?? 0,
            $r['total_respuestas'] ?? 0,
            !empty($r['tiene_respuesta']) ? 'Sí' : 'No',
            $r['ultima_decision'] ?? '',
            $r['ultimo_analista'] ?? '',
            $r['fecha_ultima_respuesta'] ?? '',
            !empty($r['es_seleccionada']) ? 'Sí' : 'No',
            $r['decision_seleccionada'] ?? '',
            $r['razon_seleccionada'] ?? '',
            $r['tasa_seleccionada'] ?? '',
            $r['valor_financiar_seleccionada'] ?? '',
            $r['abono_seleccionada'] ?? '',
            $r['plazo_seleccionada'] ?? '',
            $r['letra_seleccionada'] ?? '',
            $r['letra_quincenal_seleccionada'] ?? '',
            $r['promocion_seleccionada'] ?? '',
            $r['cuantia_seleccionada'] ?? '',
            $r['comentarios_seleccionada'] ?? '',
            $r['encargado_seleccionada'] ?? '',
            $r['fecha_eval_seleccionada'] ?? '',
            $r['vehiculo_label'] ?? '',
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}
