<?php
/**
 * Seguimiento: formulario público (financiamiento_registros) con o sin solicitud Motus.
 */

declare(strict_types=1);

require_once __DIR__ . '/reportes_fin_demografia_data.php';

/**
 * @return array{desde:string,hasta:string,vinculo:string}
 */
function rep_segfin_parse_filtros(): array
{
    $desde = isset($_GET['desde']) ? trim((string) $_GET['desde']) : '';
    $hasta = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : '';
    $vinculo = isset($_GET['vinculo']) ? trim((string) $_GET['vinculo']) : '';
    if (!in_array($vinculo, ['', 'con', 'sin'], true)) {
        $vinculo = '';
    }

    return ['desde' => $desde, 'hasta' => $hasta, 'vinculo' => $vinculo];
}

/**
 * @return array<int,array<string,mixed>>
 */
function rep_segfin_fetch_raw(PDO $pdo, string $d1, string $d2): array
{
    $hasFinCol = rep_fin_columna_existe($pdo, 'solicitudes_credito', 'financiamiento_registro_id');
    $hasScOnFr = rep_fin_columna_existe($pdo, 'financiamiento_registros', 'solicitud_credito_id');
    $hasIdVendedor = rep_fin_columna_existe($pdo, 'financiamiento_registros', 'id_vendedor');

    $joinSc = 'LEFT JOIN solicitudes_credito sc ON 1=0';
    if ($hasFinCol && $hasScOnFr) {
        $joinSc = 'LEFT JOIN solicitudes_credito sc ON sc.financiamiento_registro_id = fr.id OR sc.id = fr.solicitud_credito_id';
    } elseif ($hasFinCol) {
        $joinSc = 'LEFT JOIN solicitudes_credito sc ON sc.financiamiento_registro_id = fr.id';
    } elseif ($hasScOnFr) {
        $joinSc = 'LEFT JOIN solicitudes_credito sc ON sc.id = fr.solicitud_credito_id';
    }

    $joinEv = $hasIdVendedor
        ? 'LEFT JOIN ejecutivos_ventas ev ON ev.id = fr.id_vendedor'
        : 'LEFT JOIN ejecutivos_ventas ev ON 1=0';

    $sql = "
        SELECT
            fr.id,
            fr.fecha_creacion,
            fr.cliente_nombre,
            fr.cliente_sexo,
            fr.cliente_edad,
            fr.cliente_nacimiento,
            fr.empresa_salario,
            fr.empresa_ocupacion,
            fr.empresa_nombre,
            fr.otros_ingresos,
            fr.ocupacion_otros,
            fr.trabajo_anterior,
            fr.empresa_direccion,
            fr.barriada_calle_casa,
            fr.prov_dist_corr,
            fr.celular_cliente,
            fr.email_vendedor,
            fr.marca_auto,
            fr.modelo_auto,
            fr.anio_auto,
            sc.id AS solicitud_id,
            sc.estado AS solicitud_estado,
            sc.perfil_financiero AS perfil_motus,
            sc.ingreso AS ingreso_motus,
            sc.genero AS genero_motus,
            sc.edad AS edad_motus,
            sc.nombre_cliente AS nombre_motus,
            sc.cedula AS cedula_motus,
            ev.nombre AS vendedor_nombre
        FROM financiamiento_registros fr
        {$joinSc}
        {$joinEv}
        WHERE DATE(fr.fecha_creacion) BETWEEN :d1 AND :d2
        ORDER BY fr.fecha_creacion DESC
        LIMIT 20000
    ";

    $st = $pdo->prepare($sql);
    $st->execute(['d1' => $d1, 'd2' => $d2]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string,mixed> $r
 * @return array<string,mixed>
 */
function rep_segfin_enriquecer_fila(array $r): array
{
    $e = rep_fin_enriquecer_fila_publica($r);
    $e['cliente_sexo'] = $r['cliente_sexo'] ?? '';
    $sid = isset($r['solicitud_id']) && $r['solicitud_id'] !== null && $r['solicitud_id'] !== ''
        ? (int) $r['solicitud_id']
        : null;

    $e['id_sol_digital'] = (int) ($r['id'] ?? 0);
    $e['id_sol_motus'] = $sid;
    $e['tiene_solicitud_motus'] = $sid !== null && $sid > 0;
    $e['vinculo_label'] = $e['tiene_solicitud_motus'] ? 'Con solicitud Motus' : 'Sin solicitud Motus';

    $vNombre = trim((string) ($r['vendedor_nombre'] ?? ''));
    $e['vendedor'] = $vNombre !== '' ? $vNombre : trim((string) ($r['email_vendedor'] ?? ''));
    $e['telefono'] = trim((string) ($r['celular_cliente'] ?? ''));

    $veh = [];
    foreach (['marca_auto', 'modelo_auto', 'anio_auto'] as $k) {
        $v = trim((string) ($r[$k] ?? ''));
        if ($v !== '') {
            $veh[] = $v;
        }
    }
    $e['unidad_vehiculo'] = $veh !== [] ? implode(' ', $veh) : '';

    $e['solicitud_id'] = $sid;
    $e['solicitud_estado'] = $sid ? (string) ($r['solicitud_estado'] ?? '') : '';
    $e['perfil_motus'] = $sid ? (string) ($r['perfil_motus'] ?? '') : '';
    $e['ingreso_motus'] = $sid && isset($r['ingreso_motus']) && $r['ingreso_motus'] !== '' ? $r['ingreso_motus'] : null;
    $e['genero_motus'] = $sid ? (string) ($r['genero_motus'] ?? '') : '';
    $e['edad_motus'] = $sid && isset($r['edad_motus']) && $r['edad_motus'] !== '' ? $r['edad_motus'] : null;
    $e['nombre_motus'] = $sid ? (string) ($r['nombre_motus'] ?? '') : '';
    $e['cedula_motus'] = $sid ? (string) ($r['cedula_motus'] ?? '') : '';

    if ($sid) {
        $tipoFr = rep_fin_tipo_perfil_desde_etiqueta((string) ($e['perfil_estimado'] ?? ''));
        $pm = (string) ($e['perfil_motus'] ?? '');
        $e['perfil_motus_coincide'] = ($pm !== '' && $tipoFr === $pm);
        $e['genero_motus_coincide'] = rep_fin_generos_equivalentes((string) ($e['genero_label'] ?? ''), (string) ($e['genero_motus'] ?? ''));
        $e['perfil_coincide_txt'] = !empty($e['perfil_motus_coincide']) ? 'Sí' : 'No';
        $e['genero_coincide_txt'] = !empty($e['genero_motus_coincide']) ? 'Sí' : 'No';
    } else {
        $e['perfil_motus_coincide'] = null;
        $e['genero_motus_coincide'] = null;
        $e['perfil_coincide_txt'] = '—';
        $e['genero_coincide_txt'] = '—';
    }

    return $e;
}

/**
 * @param array{desde:string,hasta:string,vinculo:string} $filt
 */
function rep_segfin_pasar_filtro_vinculo(array $e, array $filt): bool
{
    if ($filt['vinculo'] === 'con' && empty($e['tiene_solicitud_motus'])) {
        return false;
    }
    if ($filt['vinculo'] === 'sin' && !empty($e['tiene_solicitud_motus'])) {
        return false;
    }

    return true;
}

/** @return list<string> */
function rep_segfin_export_headers(): array
{
    return [
        'ID financiamiento',
        'Fecha',
        'Cliente (público)',
        'Sexo formulario',
        'Género (agrupado)',
        'Edad (calc.)',
        'Rango edad',
        'Salario USD (form.)',
        'Rango salario',
        'Perfil estimado',
        'Sector estimado',
        'ID solicitud',
        'Estado Motus',
        'Perfil Motus',
        'Ingreso Motus',
        'Género Motus',
        'Edad Motus',
        'Nombre Motus',
        'Cédula Motus',
        '¿Perfil coincide?',
        '¿Género coincide?',
        'Vendedor',
        '# de Telefono',
        'Unidad/ Vehículo',
        'ID Sol Digital',
        'ID Sol MOTUS',
        'Vínculo Motus',
    ];
}

/**
 * @param array<string,mixed> $e
 * @return list<string|int|float|null>
 */
function rep_segfin_export_row(array $e): array
{
    return [
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
        $e['ingreso_motus'] ?? '',
        $e['genero_motus'] ?? '',
        $e['edad_motus'] ?? '',
        $e['nombre_motus'] ?? '',
        $e['cedula_motus'] ?? '',
        $e['perfil_coincide_txt'] ?? '',
        $e['genero_coincide_txt'] ?? '',
        $e['vendedor'] ?? '',
        $e['telefono'] ?? '',
        $e['unidad_vehiculo'] ?? '',
        $e['id_sol_digital'] ?? '',
        $e['id_sol_motus'] ?? '',
        $e['vinculo_label'] ?? '',
    ];
}

/**
 * @param array{desde?:string,hasta?:string,vinculo?:string}|null $filtOverride
 * @return array<string,mixed>
 */
function rep_segfin_build_reporte(PDO $pdo, ?array $filtOverride = null): array
{
    if (!rep_fin_tabla_existe($pdo, 'financiamiento_registros')) {
        return ['success' => false, 'message' => 'No existe la tabla financiamiento_registros en esta base de datos.'];
    }

    $filt = $filtOverride ?? rep_segfin_parse_filtros();
    if (!isset($filt['vinculo'])) {
        $filt['vinculo'] = '';
    }
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);

    try {
        $raw = rep_segfin_fetch_raw($pdo, $d1, $d2);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1146) {
            return ['success' => false, 'message' => 'No existe la tabla financiamiento_registros.'];
        }
        throw $e;
    }

    $filas = [];
    $conMotus = 0;
    $sinMotus = 0;
    $seenFr = [];

    foreach ($raw as $row) {
        $frId = (int) ($row['id'] ?? 0);
        if ($frId <= 0 || isset($seenFr[$frId])) {
            continue;
        }
        $seenFr[$frId] = true;

        $e = rep_segfin_enriquecer_fila($row);
        if (!rep_segfin_pasar_filtro_vinculo($e, $filt)) {
            continue;
        }

        if ($e['tiene_solicitud_motus']) {
            $conMotus++;
        } else {
            $sinMotus++;
        }
        $filas[] = $e;
    }

    return [
        'success' => true,
        'filtros' => array_merge($filt, ['fecha_desde' => $d1, 'fecha_hasta' => $d2]),
        'kpis' => [
            'total' => count($filas),
            'con_motus' => $conMotus,
            'sin_motus' => $sinMotus,
        ],
        'pie_vinculo' => [
            ['label' => 'Con solicitud Motus', 'total' => $conMotus],
            ['label' => 'Sin solicitud Motus', 'total' => $sinMotus],
        ],
        'filas' => $filas,
        'nota' => 'Incluye todos los envíos del formulario público (enlace). La solicitud Motus se detecta por financiamiento_registro_id o solicitud_credito_id.',
    ];
}

/**
 * @param array{desde:string,hasta:string,vinculo:string} $filt
 * @return array{headers:list<string>,rows:list<array<int,string|float|null>>}
 */
function rep_segfin_export_pack(PDO $pdo, array $filt): array
{
    $pack = rep_segfin_build_reporte($pdo, $filt);
    if (empty($pack['success'])) {
        return ['headers' => rep_segfin_export_headers(), 'rows' => []];
    }

    $rows = [];
    foreach ($pack['filas'] ?? [] as $e) {
        $rows[] = rep_segfin_export_row($e);
    }

    return ['headers' => rep_segfin_export_headers(), 'rows' => $rows];
}
