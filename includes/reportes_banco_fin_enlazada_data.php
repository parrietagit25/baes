<?php
/**
 * Reporte Sol. Financiamiento público + Motus enlazada, filtrado por entidad banco (admin banco).
 */

declare(strict_types=1);

require_once __DIR__ . '/reportes_fin_demografia_data.php';
require_once __DIR__ . '/reportes_vehiculos_data.php';

/** @return array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string} */
function rep_fin_banco_parse_filtros(): array
{
    $filt = rep_fin_parse_filtros();
    unset($filt['estado_sc']);

    return $filt;
}

/**
 * @param array<string,mixed> $r
 * @param array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string} $filt
 */
function rep_fin_banco_pasar_filtro(array $r, array $filt): bool
{
    return rep_fin_pasar_filtro($r, array_merge($filt, ['estado_sc' => '']), false);
}

/**
 * @param array<string,mixed> $r
 */
function rep_fin_banco_abono_pct(array $r): ?float
{
    if (isset($r['v_abono_pct']) && $r['v_abono_pct'] !== null && $r['v_abono_pct'] !== '') {
        return (float) $r['v_abono_pct'];
    }
    if (isset($r['abono_porcentaje']) && $r['abono_porcentaje'] !== null && $r['abono_porcentaje'] !== '') {
        return (float) $r['abono_porcentaje'];
    }
    $precio = null;
    if (isset($r['v_precio']) && $r['v_precio'] !== null && $r['v_precio'] !== '') {
        $precio = (float) $r['v_precio'];
    } elseif (isset($r['precio_especial']) && $r['precio_especial'] !== null && $r['precio_especial'] !== '') {
        $precio = (float) $r['precio_especial'];
    }
    $monto = null;
    if (isset($r['v_abono_monto']) && $r['v_abono_monto'] !== null && $r['v_abono_monto'] !== '') {
        $monto = (float) $r['v_abono_monto'];
    } elseif (isset($r['abono_monto']) && $r['abono_monto'] !== null && $r['abono_monto'] !== '') {
        $monto = (float) $r['abono_monto'];
    }
    if ($precio !== null && $precio > 0 && $monto !== null && $monto >= 0) {
        return round(($monto / $precio) * 100, 2);
    }

    return null;
}

/**
 * @param array<int,array<string,mixed>> $filas
 * @return array<string,mixed>
 */
function rep_fin_banco_agregar_abono(array $filas): array
{
    $orden = ['Sin dato', '< 5%', '5-9%', '10-19%', '20-29%', '30%+'];
    $porRango = array_fill_keys($orden, 0);
    $sumPct = 0.0;
    $nPct = 0;

    foreach ($filas as $r) {
        $pct = rep_fin_banco_abono_pct($r);
        $rango = rep_veh_rango_abono($pct);
        if (!isset($porRango[$rango])) {
            $porRango[$rango] = 0;
        }
        $porRango[$rango]++;
        if ($pct !== null && $pct >= 0) {
            $sumPct += $pct;
            $nPct++;
        }
    }

    return [
        'por_rango_abono_pct' => $porRango,
        'orden_rangos_abono' => $orden,
        'abono_pct_promedio' => $nPct > 0 ? round($sumPct / $nPct, 1) : null,
    ];
}

/**
 * @return array{rows:array<int,array<string,mixed>>,error:?string}
 */
function rep_fin_fetch_enlazada_banco(PDO $pdo, string $d1, string $d2, int $bancoId): array
{
    if (!rep_fin_columna_existe($pdo, 'solicitudes_credito', 'financiamiento_registro_id')) {
        return [
            'rows' => [],
            'error' => 'Falta la columna solicitudes_credito.financiamiento_registro_id. Ejecute database/migracion_solicitud_financiamiento_registro_id.sql',
        ];
    }

    $joinVeh = rep_veh_tabla_existe($pdo, 'vehiculos_solicitud');
    $joinVehSql = '';
    $vehCols = 'NULL AS v_abono_pct, NULL AS v_abono_monto, NULL AS v_precio';
    if ($joinVeh) {
        $joinVehSql = '
            LEFT JOIN vehiculos_solicitud v ON v.id = (
                SELECT MIN(v2.id) FROM vehiculos_solicitud v2 WHERE v2.solicitud_id = sc.id
            )';
        $vehCols = 'v.abono_porcentaje AS v_abono_pct, v.abono_monto AS v_abono_monto, v.precio AS v_precio';
    }

    $sql = "
        SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_sexo, fr.cliente_edad, fr.cliente_nacimiento, fr.empresa_salario,
            fr.empresa_ocupacion, fr.empresa_nombre, fr.otros_ingresos, fr.ocupacion_otros, fr.trabajo_anterior,
            fr.empresa_direccion, fr.barriada_calle_casa, fr.prov_dist_corr,
            sc.id AS solicitud_id, sc.estado AS solicitud_estado, sc.perfil_financiero AS perfil_motus,
            sc.ingreso AS ingreso_motus, sc.genero AS genero_motus, sc.edad AS edad_motus,
            sc.nombre_cliente AS nombre_motus, sc.cedula AS cedula_motus,
            sc.abono_porcentaje, sc.abono_monto, sc.precio_especial,
            {$vehCols}
        FROM financiamiento_registros fr
        INNER JOIN solicitudes_credito sc ON sc.financiamiento_registro_id = fr.id
        INNER JOIN usuarios_banco_solicitudes ubs ON ubs.solicitud_id = sc.id AND ubs.estado = 'activo'
        INNER JOIN usuarios u ON u.id = ubs.usuario_banco_id AND u.banco_id = :banco_id
        {$joinVehSql}
        WHERE DATE(fr.fecha_creacion) BETWEEN :d1 AND :d2
        GROUP BY fr.id, sc.id
        ORDER BY fr.fecha_creacion DESC
        LIMIT 15000
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['d1' => $d1, 'd2' => $d2, 'banco_id' => $bancoId]);

    return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'error' => null];
}

/**
 * @return array<string,mixed>
 */
function rep_fin_build_reporte_enlazada_banco(PDO $pdo, int $bancoId): array
{
    if (!rep_fin_tabla_existe($pdo, 'financiamiento_registros')) {
        return ['success' => false, 'message' => 'No existe la tabla financiamiento_registros en esta base de datos.'];
    }

    $filt = rep_fin_banco_parse_filtros();
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);
    $pack = rep_fin_fetch_enlazada_banco($pdo, $d1, $d2, $bancoId);
    if ($pack['error'] !== null) {
        return ['success' => false, 'message' => $pack['error']];
    }

    $filas = [];
    foreach ($pack['rows'] as $row) {
        $e = rep_fin_enriquecer_fila_enlazada($row);
        $e['abono_pct_resuelto'] = rep_fin_banco_abono_pct($row);
        $e['rango_abono_pct'] = rep_veh_rango_abono($e['abono_pct_resuelto']);
        if (rep_fin_banco_pasar_filtro($e, $filt)) {
            $filas[] = $e;
        }
    }

    $agg = rep_fin_agregar_distribuciones($filas);
    $extra = rep_fin_agregar_enlazada_extra($filas);
    $abono = rep_fin_banco_agregar_abono($filas);

    $muestra = [];
    foreach (array_slice($filas, 0, 120) as $m) {
        $muestra[] = [
            'id' => $m['id'] ?? null,
            'fecha_creacion' => $m['fecha_creacion'] ?? null,
            'cliente_nombre' => $m['cliente_nombre'] ?? null,
            'genero_label' => $m['genero_label'] ?? null,
            'edad_calculada' => $m['edad_calculada'] ?? null,
            'rango_salario_usd' => $m['rango_salario_usd'] ?? null,
            'perfil_estimado' => $m['perfil_estimado'] ?? null,
            'sector_estimado' => $m['sector_estimado'] ?? null,
            'solicitud_id' => $m['solicitud_id'] ?? null,
            'solicitud_estado' => $m['solicitud_estado'] ?? null,
            'perfil_motus' => $m['perfil_motus'] ?? null,
            'genero_motus' => $m['genero_motus'] ?? null,
            'abono_pct_resuelto' => $m['abono_pct_resuelto'] ?? null,
            'rango_abono_pct' => $m['rango_abono_pct'] ?? null,
            'perfil_motus_coincide' => $m['perfil_motus_coincide'] ?? false,
            'genero_motus_coincide' => $m['genero_motus_coincide'] ?? false,
        ];
    }

    return array_merge([
        'success' => true,
        'filtros' => array_merge($filt, ['fecha_desde' => $d1, 'fecha_hasta' => $d2, 'banco_id' => $bancoId]),
        'nota_metodologica' => 'Solo solicitudes Motus vinculadas al formulario público y asignadas activamente a usuarios de su banco.',
        'muestra' => $muestra,
    ], $agg, ['enlazada' => $extra], ['abono' => $abono]);
}

/**
 * @param array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string} $filt
 * @return array{array<int,string>,array<int,array<int,string|int|float|bool|null>>}
 */
function rep_fin_filas_export_enlazada_banco(PDO $pdo, int $bancoId, array $filt): array
{
    if (!rep_fin_columna_existe($pdo, 'solicitudes_credito', 'financiamiento_registro_id')) {
        return [[], []];
    }
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);
    $pack = rep_fin_fetch_enlazada_banco($pdo, $d1, $d2, $bancoId);
    $headers = [
        'ID financiamiento', 'Fecha', 'Cliente (público)', 'Sexo formulario', 'Género (agrupado)', 'Edad (calc.)', 'Rango edad',
        'Salario USD (form.)', 'Rango salario', 'Perfil estimado', 'Sector estimado',
        'ID solicitud', 'Estado Motus', 'Perfil Motus', 'Género Motus', 'Abono %', 'Rango abono %',
        '¿Perfil coincide?', '¿Género coincide?',
    ];
    $rows = [];
    foreach ($pack['rows'] as $row) {
        $e = rep_fin_enriquecer_fila_enlazada($row);
        $e['abono_pct_resuelto'] = rep_fin_banco_abono_pct($row);
        $e['rango_abono_pct'] = rep_veh_rango_abono($e['abono_pct_resuelto']);
        if (!rep_fin_banco_pasar_filtro($e, $filt)) {
            continue;
        }
        $rows[] = [
            $e['id'] ?? '',
            $e['fecha_creacion'] ?? '',
            $e['cliente_nombre'] ?? '',
            $e['cliente_sexo'] ?? '',
            $e['genero_label'] ?? '',
            $e['edad_calculada'] ?? '',
            $e['rango_edad'] ?? '',
            $e['empresa_salario'] ?? '',
            $e['rango_salario_usd'] ?? '',
            $e['perfil_estimado'] ?? '',
            $e['sector_estimado'] ?? '',
            $e['solicitud_id'] ?? '',
            $e['solicitud_estado'] ?? '',
            $e['perfil_motus'] ?? '',
            $e['genero_motus'] ?? '',
            $e['abono_pct_resuelto'] ?? '',
            $e['rango_abono_pct'] ?? '',
            !empty($e['perfil_motus_coincide']) ? 'Sí' : 'No',
            !empty($e['genero_motus_coincide']) ? 'Sí' : 'No',
        ];
    }

    return [$headers, $rows];
}
