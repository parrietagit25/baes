<?php
/**
 * Seguimiento: formulario público (financiamiento_registros) con o sin solicitud Motus.
 */

declare(strict_types=1);

require_once __DIR__ . '/reportes_fin_demografia_data.php';
require_once __DIR__ . '/solicitud_vehiculo_helper.php';

/**
 * @return array{desde:string,hasta:string,vinculo:string}
 */
function rep_segfin_parse_filtros(): array
{
    $desde = isset($_GET['desde']) ? trim((string) $_GET['desde']) : (isset($_POST['desde']) ? trim((string) $_POST['desde']) : '');
    $hasta = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : (isset($_POST['hasta']) ? trim((string) $_POST['hasta']) : '');
    $vinculo = isset($_GET['vinculo']) ? trim((string) $_GET['vinculo']) : (isset($_POST['vinculo']) ? trim((string) $_POST['vinculo']) : '');
    if (!in_array($vinculo, ['', 'con', 'sin'], true)) {
        $vinculo = '';
    }
    // Normalizar fechas a Y-m-d si llegan con hora u otro formato.
    foreach (['desde' => &$desde, 'hasta' => &$hasta] as $_k => &$_v) {
        if ($_v !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $_v, $m)) {
            $_v = $m[1];
        }
    }
    unset($_v);

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

    $sqlCamposReserva = '';
    if ($joinSc !== 'LEFT JOIN solicitudes_credito sc ON 1=0'
        && rep_fin_tabla_existe($pdo, 'vehiculos_solicitud')
        && rep_fin_tabla_existe($pdo, 'reportes_reservas_lineas')) {
        $sqlCamposReserva = ',' . solicitud_sql_campos_vehiculo_reserva('sc');
    }

    $sqlClienteCorreo = rep_fin_columna_existe($pdo, 'financiamiento_registros', 'cliente_correo')
        ? 'fr.cliente_correo'
        : 'NULL AS cliente_correo';
    $sqlSolicitudEmail = $joinSc !== 'LEFT JOIN solicitudes_credito sc ON 1=0'
        && rep_fin_columna_existe($pdo, 'solicitudes_credito', 'email')
        ? 'sc.email AS solicitud_email'
        : 'NULL AS solicitud_email';

    $hasEvalSel = $joinSc !== 'LEFT JOIN solicitudes_credito sc ON 1=0'
        && rep_fin_columna_existe($pdo, 'solicitudes_credito', 'evaluacion_seleccionada')
        && rep_fin_tabla_existe($pdo, 'evaluaciones_banco');
    $hasUbs = rep_fin_tabla_existe($pdo, 'usuarios_banco_solicitudes');
    $hasBancos = rep_fin_tabla_existe($pdo, 'bancos');
    $hasRazon = $hasEvalSel && rep_fin_columna_existe($pdo, 'evaluaciones_banco', 'razon');
    $hasCuantia = $hasEvalSel && rep_fin_columna_existe($pdo, 'evaluaciones_banco', 'cuantia');
    $hasLetraQ = $hasEvalSel && rep_fin_columna_existe($pdo, 'evaluaciones_banco', 'letra_quincenal');

    $joinBanco = '';
    $sqlBancoCampos = '
            NULL AS banco_nombre,
            NULL AS banco_agente_nombre,
            NULL AS banco_agente_apellido,
            NULL AS banco_decision,
            NULL AS banco_razon,
            NULL AS banco_tasa,
            NULL AS banco_valor_financiar,
            NULL AS banco_abono,
            NULL AS banco_plazo,
            NULL AS banco_letra,
            NULL AS banco_letra_quincenal,
            NULL AS banco_promocion,
            NULL AS banco_cuantia,
            NULL AS banco_comentarios,
            NULL AS banco_fecha_evaluacion,
            0 AS enviada_a_banco';

    if ($hasEvalSel) {
        $joinBanco = ' LEFT JOIN evaluaciones_banco eb_sel ON eb_sel.id = sc.evaluacion_seleccionada';
        if ($hasUbs) {
            $joinBanco .= ' LEFT JOIN usuarios_banco_solicitudes ubs_sel ON ubs_sel.id = eb_sel.usuario_banco_id';
            $joinBanco .= ' LEFT JOIN usuarios u_banco ON u_banco.id = ubs_sel.usuario_banco_id';
            if ($hasBancos) {
                $joinBanco .= ' LEFT JOIN bancos b_sel ON b_sel.id = u_banco.banco_id';
            }
        }
        $sqlRazon = $hasRazon ? 'eb_sel.razon' : 'NULL';
        $sqlCuantia = $hasCuantia ? 'eb_sel.cuantia' : 'NULL';
        $sqlLetraQ = $hasLetraQ ? 'eb_sel.letra_quincenal' : 'NULL';
        $sqlBancoNombre = ($hasUbs && $hasBancos) ? 'b_sel.nombre' : 'NULL';
        $sqlAgenteNom = $hasUbs ? 'u_banco.nombre' : 'NULL';
        $sqlAgenteApe = $hasUbs ? 'u_banco.apellido' : 'NULL';

        $enviadaExpr = '0';
        if ($hasUbs) {
            $enviadaExpr = '(CASE WHEN sc.id IS NOT NULL AND (
                EXISTS (SELECT 1 FROM usuarios_banco_solicitudes ubsx WHERE ubsx.solicitud_id = sc.id)
                OR EXISTS (SELECT 1 FROM evaluaciones_banco ebx WHERE ebx.solicitud_id = sc.id)
            ) THEN 1 ELSE 0 END)';
        } else {
            $enviadaExpr = '(CASE WHEN sc.id IS NOT NULL AND EXISTS (
                SELECT 1 FROM evaluaciones_banco ebx WHERE ebx.solicitud_id = sc.id
            ) THEN 1 ELSE 0 END)';
        }

        $sqlBancoCampos = "
            {$sqlBancoNombre} AS banco_nombre,
            {$sqlAgenteNom} AS banco_agente_nombre,
            {$sqlAgenteApe} AS banco_agente_apellido,
            eb_sel.decision AS banco_decision,
            {$sqlRazon} AS banco_razon,
            eb_sel.tasa_bancaria AS banco_tasa,
            eb_sel.valor_financiar AS banco_valor_financiar,
            eb_sel.abono AS banco_abono,
            eb_sel.plazo AS banco_plazo,
            eb_sel.letra AS banco_letra,
            {$sqlLetraQ} AS banco_letra_quincenal,
            eb_sel.promocion AS banco_promocion,
            {$sqlCuantia} AS banco_cuantia,
            eb_sel.comentarios AS banco_comentarios,
            eb_sel.fecha_evaluacion AS banco_fecha_evaluacion,
            {$enviadaExpr} AS enviada_a_banco";
    }

    $sqlFechaMotus = $joinSc !== 'LEFT JOIN solicitudes_credito sc ON 1=0'
        && rep_fin_columna_existe($pdo, 'solicitudes_credito', 'fecha_creacion')
        ? 'sc.fecha_creacion AS fecha_motus'
        : 'NULL AS fecha_motus';

    $whereFecha = 'DATE(fr.fecha_creacion) BETWEEN ? AND ?';
    $paramsFecha = [$d1, $d2];
    if ($joinSc !== 'LEFT JOIN solicitudes_credito sc ON 1=0'
        && rep_fin_columna_existe($pdo, 'solicitudes_credito', 'fecha_creacion')) {
        // Híbrido: entra si el formulario público O la solicitud Motus cae en el rango.
        $whereFecha = '(
            (fr.fecha_creacion IS NOT NULL AND DATE(fr.fecha_creacion) BETWEEN ? AND ?)
            OR (sc.fecha_creacion IS NOT NULL AND DATE(sc.fecha_creacion) BETWEEN ? AND ?)
        )';
        $paramsFecha = [$d1, $d2, $d1, $d2];
    }

    $sql = "
        SELECT
            fr.id,
            fr.fecha_creacion,
            {$sqlFechaMotus},
            fr.cliente_nombre,
            {$sqlClienteCorreo},
            {$sqlSolicitudEmail},
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
            ev.nombre AS vendedor_nombre,
            {$sqlBancoCampos}
            {$sqlCamposReserva}
        FROM financiamiento_registros fr
        {$joinSc}
        {$joinEv}
        {$joinBanco}
        WHERE {$whereFecha}
        ORDER BY fr.fecha_creacion DESC
        LIMIT 20000
    ";

    $st = $pdo->prepare($sql);
    $st->execute($paramsFecha);

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
    $e['fecha_motus'] = $sid ? (string) ($r['fecha_motus'] ?? '') : '';
    // Normalizar fecha formulario para comparar en filtros PHP.
    $e['fecha_formulario'] = (string) ($e['fecha_creacion'] ?? $r['fecha_creacion'] ?? '');

    $vNombre = trim((string) ($r['vendedor_nombre'] ?? ''));
    $e['vendedor'] = $vNombre !== '' ? $vNombre : trim((string) ($r['email_vendedor'] ?? ''));
    $e['telefono'] = trim((string) ($r['celular_cliente'] ?? ''));
    $emailForm = trim((string) ($r['cliente_correo'] ?? ''));
    $emailMotus = trim((string) ($r['solicitud_email'] ?? ''));
    $e['cliente_email'] = $emailForm !== '' ? $emailForm : $emailMotus;

    $e['unidad_vehiculo'] = '';
    if ($sid) {
        $e['unidad_vehiculo'] = solicitud_texto_vehiculo_lista(array_merge($r, [
            'marca_auto' => $r['marca_auto'] ?? '',
            'modelo_auto' => $r['modelo_auto'] ?? '',
            'ao_auto' => $r['anio_auto'] ?? '',
            'año_auto' => $r['anio_auto'] ?? '',
        ]));
    }

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

    $e['enviada_a_banco'] = !empty($r['enviada_a_banco']);
    $e['enviada_a_banco_txt'] = $e['enviada_a_banco'] ? 'Sí' : 'No';

    $bancoNombre = trim((string) ($r['banco_nombre'] ?? ''));
    $agente = trim(((string) ($r['banco_agente_nombre'] ?? '')) . ' ' . ((string) ($r['banco_agente_apellido'] ?? '')));
    $decisionRaw = trim((string) ($r['banco_decision'] ?? ''));
    $decisionLabel = $decisionRaw !== ''
        ? strtoupper(str_replace('_', ' ', $decisionRaw))
        : '';

    $e['banco_nombre'] = $bancoNombre;
    $e['banco_agente'] = $agente;
    $e['banco_decision'] = $decisionLabel;
    $e['banco_razon'] = trim((string) ($r['banco_razon'] ?? ''));
    $e['banco_tasa'] = $r['banco_tasa'] ?? null;
    $e['banco_valor_financiar'] = $r['banco_valor_financiar'] ?? null;
    $e['banco_abono'] = $r['banco_abono'] ?? null;
    $e['banco_plazo'] = $r['banco_plazo'] ?? null;
    $e['banco_letra'] = $r['banco_letra'] ?? null;
    $e['banco_letra_quincenal'] = $r['banco_letra_quincenal'] ?? null;
    $e['banco_promocion'] = trim((string) ($r['banco_promocion'] ?? ''));
    $e['banco_cuantia'] = $r['banco_cuantia'] ?? null;
    $e['banco_comentarios'] = trim((string) ($r['banco_comentarios'] ?? ''));
    $e['banco_fecha_evaluacion'] = $r['banco_fecha_evaluacion'] ?? null;
    $e['banco_respuesta_txt'] = $decisionLabel !== ''
        ? ($bancoNombre !== '' ? $bancoNombre . ' — ' . $decisionLabel : $decisionLabel)
        : ($e['enviada_a_banco'] ? 'Enviada (sin propuesta seleccionada)' : '—');

    return $e;
}

/**
 * Extrae Y-m-d de un datetime/string.
 */
function rep_segfin_solo_dia(?string $fecha): string
{
    $t = trim((string) $fecha);
    if ($t === '') {
        return '';
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $t, $m)) {
        return $m[1];
    }
    try {
        return (new DateTimeImmutable($t))->format('Y-m-d');
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Híbrido: pasa si la fecha del formulario público O la de Motus caen en el rango.
 *
 * @param array{desde?:string,hasta?:string,vinculo?:string} $filt
 */
function rep_segfin_pasar_filtro_fechas(array $e, string $d1, string $d2): bool
{
    $fr = rep_segfin_solo_dia((string) ($e['fecha_creacion'] ?? $e['fecha_formulario'] ?? ''));
    $motus = rep_segfin_solo_dia((string) ($e['fecha_motus'] ?? ''));
    $okFr = ($fr !== '' && $fr >= $d1 && $fr <= $d2);
    $okMotus = ($motus !== '' && $motus >= $d1 && $motus <= $d2);

    return $okFr || $okMotus;
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
        'Fecha form. público',
        'Fecha Motus',
        'Cliente (público)',
        'Email del cliente',
        'Sexo formulario',
        'Género (agrupado)',
        'Edad (calc.)',
        'Rango edad',
        'Salario USD (form.)',
        'Rango salario',
        'Perfil estimado',
        'Sector estimado',
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
        'Enviada a banco',
        'Banco (seleccionado)',
        'Agente banco',
        'Decisión seleccionada',
        'Razón banco',
        'Tasa %',
        'Precio/valor',
        'Abono banco',
        'Plazo',
        'Letra mensual',
        'Letra quincenal',
        'Promoción',
        'Cuantía',
        'Comentarios banco',
        'Fecha evaluación sel.',
    ];
}

/**
 * @param array<string,mixed> $e
 * @return list<string|int|float|null>
 */
function rep_segfin_export_row(array $e): array
{
    return [
        $e['fecha_creacion'] ?? '',
        $e['fecha_motus'] ?? '',
        $e['cliente_nombre'] ?? '',
        $e['cliente_email'] ?? '',
        $e['cliente_sexo'] ?? '',
        $e['genero_label'] ?? '',
        $e['edad_calculada'] ?? '',
        $e['rango_edad'] ?? '',
        $e['empresa_salario'] ?? '',
        $e['rango_salario_usd'] ?? '',
        $e['perfil_estimado'] ?? '',
        $e['sector_estimado'] ?? '',
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
        $e['enviada_a_banco_txt'] ?? '',
        $e['banco_nombre'] ?? '',
        $e['banco_agente'] ?? '',
        $e['banco_decision'] ?? '',
        $e['banco_razon'] ?? '',
        $e['banco_tasa'] ?? '',
        $e['banco_valor_financiar'] ?? '',
        $e['banco_abono'] ?? '',
        $e['banco_plazo'] ?? '',
        $e['banco_letra'] ?? '',
        $e['banco_letra_quincenal'] ?? '',
        $e['banco_promocion'] ?? '',
        $e['banco_cuantia'] ?? '',
        $e['banco_comentarios'] ?? '',
        $e['banco_fecha_evaluacion'] ?? '',
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
    $enviadaBanco = 0;
    $noEnviadaBanco = 0;
    $seenFr = [];

    foreach ($raw as $row) {
        $frId = (int) ($row['id'] ?? 0);
        if ($frId <= 0 || isset($seenFr[$frId])) {
            continue;
        }
        $seenFr[$frId] = true;

        $e = rep_segfin_enriquecer_fila($row);
        if (!rep_segfin_pasar_filtro_fechas($e, $d1, $d2)) {
            continue;
        }
        if (!rep_segfin_pasar_filtro_vinculo($e, $filt)) {
            continue;
        }

        if ($e['tiene_solicitud_motus']) {
            $conMotus++;
        } else {
            $sinMotus++;
        }
        if (!empty($e['enviada_a_banco'])) {
            $enviadaBanco++;
        } else {
            $noEnviadaBanco++;
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
            'enviada_banco' => $enviadaBanco,
            'no_enviada_banco' => $noEnviadaBanco,
        ],
        'pie_vinculo' => [
            ['label' => 'Con solicitud Motus', 'total' => $conMotus],
            ['label' => 'Sin solicitud Motus', 'total' => $sinMotus],
        ],
        'pie_enviada_banco' => [
            ['label' => 'Enviadas a banco', 'total' => $enviadaBanco],
            ['label' => 'No enviadas a banco', 'total' => $noEnviadaBanco],
        ],
        'filas' => $filas,
        'nota' => 'Filtro de fechas híbrido: incluye registros cuyo formulario público (fecha creación Sol Digital) o cuya solicitud Motus (fecha creación) caiga dentro del rango Desde/Hasta.',
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
