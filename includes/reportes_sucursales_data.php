<?php
/**
 * Datos para Rep. Sucursales (ejecutivos_ventas.sucursal = sigla de sucursal o SP-* supervisor).
 * Fuentes: credito (solicitudes_credito) | financiamiento (financiamiento_registros).
 */

const REPORTES_SUCURSALES_ESTADOS = [
    'Nueva',
    'En Revisión Banco',
    'Aprobada',
    'Rechazada',
    'Completada',
    'Desistimiento',
];

const REPORTES_SUCURSALES_ESTADO_SOLO_FIN = 'Solo Sol. Financiamiento';

const REPORTES_SUCURSALES_NOMBRES = [
    'CH' => 'Chiriquí',
    'CV' => 'Costa Verde',
    'TBM' => 'Tumbamuerto',
    'VIS' => 'Vía Israel',
    'BDC' => 'BDC',
    'NN' => 'Supervisor nacional',
];

function reportes_sucursales_normalizar_fuente(?string $fuente): string
{
    $f = strtolower(trim((string) $fuente));

    return $f === 'financiamiento' ? 'financiamiento' : 'credito';
}

/** @return list<string> */
function reportes_sucursales_lista_estados(string $fuente = 'credito'): array
{
    $fuente = reportes_sucursales_normalizar_fuente($fuente);
    if ($fuente === 'financiamiento') {
        return array_merge([REPORTES_SUCURSALES_ESTADO_SOLO_FIN], REPORTES_SUCURSALES_ESTADOS);
    }

    return REPORTES_SUCURSALES_ESTADOS;
}

/** @return array<string, int> */
function reportes_sucursales_estados_vacios(string $fuente = 'credito'): array
{
    $out = [];
    foreach (reportes_sucursales_lista_estados($fuente) as $e) {
        $out[$e] = 0;
    }
    $out['total'] = 0;

    return $out;
}

function reportes_sucursales_tabla_existe(PDO $pdo, string $tabla): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$tabla]);

    return (int) $st->fetchColumn() > 0;
}

function reportes_sucursales_columna_existe(PDO $pdo, string $tabla, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$tabla, $col]);

    return (int) $st->fetchColumn() > 0;
}

/**
 * @return list<array{estado: string, fecha_creacion: string, ejecutivo_ventas_id: int|null}>
 */
function reportes_sucursales_cargar_filas(PDO $pdo, string $fuente, string $d1, string $d2): array
{
    $fuente = reportes_sucursales_normalizar_fuente($fuente);
    if ($fuente === 'credito') {
        $st = $pdo->prepare('
            SELECT s.estado, s.fecha_creacion, s.ejecutivo_ventas_id
            FROM solicitudes_credito s
            WHERE s.fecha_creacion IS NOT NULL
              AND DATE(s.fecha_creacion) BETWEEN ? AND ?
        ');
        $st->execute([$d1, $d2]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (!reportes_sucursales_tabla_existe($pdo, 'financiamiento_registros')) {
        return [];
    }

    $joinSc = reportes_sucursales_columna_existe($pdo, 'financiamiento_registros', 'solicitud_credito_id');
    if ($joinSc) {
        $soloFin = REPORTES_SUCURSALES_ESTADO_SOLO_FIN;
        $sql = "
            SELECT
                CASE
                    WHEN fr.solicitud_credito_id IS NOT NULL
                         AND sc.estado IS NOT NULL
                         AND TRIM(sc.estado) <> ''
                    THEN sc.estado
                    ELSE '{$soloFin}'
                END AS estado,
                fr.fecha_creacion,
                fr.id_vendedor AS ejecutivo_ventas_id
            FROM financiamiento_registros fr
            LEFT JOIN solicitudes_credito sc ON sc.id = fr.solicitud_credito_id
            WHERE fr.fecha_creacion IS NOT NULL
              AND DATE(fr.fecha_creacion) BETWEEN ? AND ?
        ";
    } else {
        $sql = '
            SELECT
                \'' . REPORTES_SUCURSALES_ESTADO_SOLO_FIN . '\' AS estado,
                fr.fecha_creacion,
                fr.id_vendedor AS ejecutivo_ventas_id
            FROM financiamiento_registros fr
            WHERE fr.fecha_creacion IS NOT NULL
              AND DATE(fr.fecha_creacion) BETWEEN ? AND ?
        ';
    }
    $st = $pdo->prepare($sql);
    $st->execute([$d1, $d2]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{0:string,1:string} Y-m-d
 */
function reportes_sucursales_rango_fechas(?string $desde, ?string $hasta): array
{
    require_once __DIR__ . '/reportes_fin_demografia_data.php';

    return rep_fin_rango_fechas_efectivo((string) ($desde ?? ''), (string) ($hasta ?? ''));
}

/**
 * Meses (Y-m) entre dos fechas inclusive, máx. 36.
 *
 * @return array<int,string>
 */
function reportes_sucursales_meses_en_rango(string $d1, string $d2): array
{
    $out = [];
    try {
        $cur = new DateTimeImmutable(substr($d1, 0, 7) . '-01');
        $end = new DateTimeImmutable(substr($d2, 0, 7) . '-01');
    } catch (Throwable $e) {
        return $out;
    }
    $n = 0;
    while ($cur <= $end && $n < 36) {
        $out[] = $cur->format('Y-m');
        $cur = $cur->modify('+1 month');
        $n++;
    }

    return $out;
}

/**
 * @return array{tipo: string, codigo: string, sigla: string, nombre_sucursal: string, es_supervisor: bool}
 */
function reportes_sucursales_clasificar_sigla(?string $sucursalRaw): array
{
    $sigla = strtoupper(trim((string) $sucursalRaw));
    if ($sigla === '') {
        return [
            'tipo' => 'sin_asignar',
            'codigo' => '',
            'sigla' => '',
            'nombre_sucursal' => 'Sin sucursal',
            'es_supervisor' => false,
        ];
    }
    if ($sigla === 'SP-NN') {
        return [
            'tipo' => 'supervisor_nacional',
            'codigo' => 'NN',
            'sigla' => $sigla,
            'nombre_sucursal' => REPORTES_SUCURSALES_NOMBRES['NN'],
            'es_supervisor' => true,
        ];
    }
    if (str_starts_with($sigla, 'SP-')) {
        $codigo = substr($sigla, 3);

        return [
            'tipo' => 'supervisor',
            'codigo' => $codigo,
            'sigla' => $sigla,
            'nombre_sucursal' => REPORTES_SUCURSALES_NOMBRES[$codigo] ?? $codigo,
            'es_supervisor' => true,
        ];
    }

    return [
        'tipo' => 'agente',
        'codigo' => $sigla,
        'sigla' => $sigla,
        'nombre_sucursal' => REPORTES_SUCURSALES_NOMBRES[$sigla] ?? $sucursalRaw,
        'es_supervisor' => false,
    ];
}

/**
 * @param array<string, int> $bucket
 */
function reportes_sucursales_sumar_estado(array &$bucket, ?string $estado): void
{
    if ($estado !== null && $estado !== '' && isset($bucket[$estado])) {
        $bucket[$estado]++;
    }
    $bucket['total']++;
}

/**
 * @return array<string, mixed>
 */
function reportes_sucursales_obtener_datos(PDO $pdo, ?string $desde = null, ?string $hasta = null, string $fuente = 'credito'): array
{
    [$d1, $d2] = reportes_sucursales_rango_fechas($desde, $hasta);
    $fuente = reportes_sucursales_normalizar_fuente($fuente);
    $listaEstados = reportes_sucursales_lista_estados($fuente);
    $estadosVacios = reportes_sucursales_estados_vacios($fuente);
    $mesesKeys = reportes_sucursales_meses_en_rango($d1, $d2);

    $ejecutivos = $pdo->query("
        SELECT id, nombre, email, sucursal
        FROM ejecutivos_ventas
        WHERE COALESCE(activo, 1) = 1
        ORDER BY sucursal, nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    $mapEjecutivo = [];
    $agentesPorCodigo = [];
    foreach ($ejecutivos as $ev) {
        $cls = reportes_sucursales_clasificar_sigla($ev['sucursal'] ?? '');
        $mapEjecutivo[(int) $ev['id']] = array_merge($ev, $cls);
        if ($cls['tipo'] === 'agente' && $cls['codigo'] !== '') {
            $agentesPorCodigo[$cls['codigo']][] = (int) $ev['id'];
        }
    }

    $solicitudes = reportes_sucursales_cargar_filas($pdo, $fuente, $d1, $d2);

    $porSucursal = [];
    $porAgente = [];
    $porSupervisor = [];
    $porEstado = array_fill_keys($listaEstados, 0);
    $sinEjecutivo = 0;
    $totalConSucursal = 0;
    $serieMensual = [];

    foreach (array_keys(REPORTES_SUCURSALES_NOMBRES) as $cod) {
        if ($cod === 'NN') {
            continue;
        }
        $porSucursal[$cod] = array_merge(
            ['codigo' => $cod, 'nombre' => REPORTES_SUCURSALES_NOMBRES[$cod], 'siglas_agentes' => $cod],
            $estadosVacios
        );
        $serieMensual[$cod] = array_fill_keys($mesesKeys, 0);
    }

    foreach ($solicitudes as $s) {
        $estado = (string) ($s['estado'] ?? '');
        if (isset($porEstado[$estado])) {
            $porEstado[$estado]++;
        }

        $evId = $s['ejecutivo_ventas_id'] !== null ? (int) $s['ejecutivo_ventas_id'] : 0;
        if ($evId <= 0 || !isset($mapEjecutivo[$evId])) {
            $sinEjecutivo++;
            continue;
        }

        $ev = $mapEjecutivo[$evId];
        $codigo = (string) $ev['codigo'];
        $fecha = $s['fecha_creacion'] ?? '';
        $ym = '';
        if ($fecha) {
            $ts = strtotime((string) $fecha);
            if ($ts !== false) {
                $ym = date('Y-m', $ts);
            }
        }

        if ($ev['tipo'] === 'agente') {
            if (!isset($porAgente[$evId])) {
                $porAgente[$evId] = array_merge(
                    [
                        'ejecutivo_id' => $evId,
                        'nombre' => $ev['nombre'],
                        'email' => $ev['email'] ?? '',
                        'sigla' => $ev['sigla'],
                        'codigo_sucursal' => $codigo,
                        'nombre_sucursal' => $ev['nombre_sucursal'],
                    ],
                    $estadosVacios
                );
            }
            reportes_sucursales_sumar_estado($porAgente[$evId], $estado);

            if ($codigo !== '' && isset($porSucursal[$codigo])) {
                reportes_sucursales_sumar_estado($porSucursal[$codigo], $estado);
                $totalConSucursal++;
                if ($ym !== '' && isset($serieMensual[$codigo][$ym])) {
                    $serieMensual[$codigo][$ym]++;
                }
            }
        }
    }

    foreach ($ejecutivos as $ev) {
        $evId = (int) $ev['id'];
        $cls = $mapEjecutivo[$evId];
        if (!$cls['es_supervisor']) {
            continue;
        }
        $codigo = (string) $cls['codigo'];
        $bucket = array_merge(
            [
                'ejecutivo_id' => $evId,
                'nombre' => $ev['nombre'],
                'email' => $ev['email'] ?? '',
                'sigla' => $cls['sigla'],
                'codigo_sucursal' => $codigo,
                'nombre_sucursal' => $cls['nombre_sucursal'],
                'agentes_en_sucursal' => isset($agentesPorCodigo[$codigo]) ? count($agentesPorCodigo[$codigo]) : 0,
            ],
            $estadosVacios
        );

        if ($cls['tipo'] === 'supervisor_nacional') {
            foreach ($porSucursal as $row) {
                foreach ($listaEstados as $e) {
                    $bucket[$e] += $row[$e];
                }
            }
            $bucket['total'] = array_sum(array_intersect_key($bucket, array_flip($listaEstados)));
        } elseif ($codigo !== '' && isset($porSucursal[$codigo])) {
            foreach ($listaEstados as $e) {
                $bucket[$e] = $porSucursal[$codigo][$e];
            }
            $bucket['total'] = $porSucursal[$codigo]['total'];
        }
        $porSupervisor[$evId] = $bucket;
    }

    usort($porAgente, static fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']));
    usort($porSupervisor, static fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']));

    $porSucursalLista = array_values($porSucursal);
    usort($porSucursalLista, static fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['nombre'], $b['nombre']));

    $lider = $porSucursalLista[0] ?? null;
    $totalSolicitudes = count($solicitudes);
    $aprobadas = ($porEstado['Aprobada'] ?? 0) + ($porEstado['Completada'] ?? 0);
    $pendientes = ($porEstado['Nueva'] ?? 0);
    if ($fuente === 'financiamiento') {
        $pendientes += ($porEstado[REPORTES_SUCURSALES_ESTADO_SOLO_FIN] ?? 0);
    }
    $cerradas = $totalSolicitudes - $pendientes;
    $tasaAprobacion = $cerradas > 0 ? round(100 * $aprobadas / $cerradas, 1) : 0;

    // Solo meses con actividad (evita cola vacía cuando el rango es muy amplio).
    $mesesConDatos = [];
    foreach ($mesesKeys as $ym) {
        $suma = 0;
        foreach ($serieMensual as $cod => $meses) {
            if ($cod === 'NN') {
                continue;
            }
            $suma += (int) ($meses[$ym] ?? 0);
        }
        if ($suma > 0) {
            $mesesConDatos[] = $ym;
        }
    }
    // Si no hay datos, conservar el rango pedido (un mes) para no romper el chart.
    if ($mesesConDatos === [] && $mesesKeys !== []) {
        $mesesConDatos = [end($mesesKeys)];
    }

    $mesesLabels = [];
    foreach ($mesesConDatos as $ym) {
        try {
            $mesesLabels[] = (new DateTimeImmutable($ym . '-01'))->format('M Y');
        } catch (Throwable $e) {
            $mesesLabels[] = $ym;
        }
    }
    $seriesLinea = [];
    foreach ($serieMensual as $cod => $meses) {
        if ($cod === 'NN') {
            continue;
        }
        $datos = [];
        foreach ($mesesConDatos as $ym) {
            $datos[] = (int) ($meses[$ym] ?? 0);
        }
        $seriesLinea[] = [
            'codigo' => $cod,
            'nombre' => REPORTES_SUCURSALES_NOMBRES[$cod] ?? $cod,
            'datos' => $datos,
        ];
    }

    $topAgentes = array_slice(array_values($porAgente), 0, 12);
    $agentesConActividad = count($porAgente);
    $supervisoresConActividad = count(array_filter(
        $porSupervisor,
        static fn($s) => ((int) ($s['total'] ?? 0)) > 0
    ));

    // Agentes con actividad por sucursal (en el rango).
    $agentesActivosPorCodigo = [];
    foreach ($porAgente as $ag) {
        $c = (string) ($ag['codigo_sucursal'] ?? '');
        if ($c === '') {
            continue;
        }
        $agentesActivosPorCodigo[$c] = ($agentesActivosPorCodigo[$c] ?? 0) + 1;
    }
    foreach ($porSupervisor as &$supRow) {
        $c = (string) ($supRow['codigo_sucursal'] ?? '');
        if (($supRow['sigla'] ?? '') === 'SP-NN') {
            $supRow['agentes_en_sucursal'] = $agentesConActividad;
        } else {
            $supRow['agentes_en_sucursal'] = $agentesActivosPorCodigo[$c] ?? 0;
        }
    }
    unset($supRow);

    return [
        'fecha_desde' => $d1,
        'fecha_hasta' => $d2,
        'fuente' => $fuente,
        'fuente_label' => $fuente === 'financiamiento' ? 'Sol. Financiamiento' : 'Solicitudes de crédito',
        'estados' => $listaEstados,
        'catalogo_sucursales' => REPORTES_SUCURSALES_NOMBRES,
        'kpis' => [
            'total_solicitudes' => $totalSolicitudes,
            'total_anio' => $totalConSucursal,
            'total_en_rango' => $totalConSucursal,
            'sin_ejecutivo' => $sinEjecutivo,
            'total_agentes' => $agentesConActividad,
            'total_supervisores' => $supervisoresConActividad,
            'tasa_aprobacion' => $tasaAprobacion,
            'sucursal_lider' => $lider && ((int) ($lider['total'] ?? 0)) > 0 ? [
                'codigo' => $lider['codigo'],
                'nombre' => $lider['nombre'],
                'total' => $lider['total'],
            ] : null,
        ],
        'por_estado' => array_map(static fn($e) => ['estado' => $e, 'total' => $porEstado[$e]], $listaEstados),
        'por_sucursal' => $porSucursalLista,
        'por_agente' => array_values($porAgente),
        'por_supervisor' => array_values($porSupervisor),
        'serie_mensual' => [
            'meses' => $mesesLabels,
            'series' => $seriesLinea,
        ],
        'top_agentes' => $topAgentes,
    ];
}
