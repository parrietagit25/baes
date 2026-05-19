<?php
/**
 * Datos para Rep. Sucursales (ejecutivos_ventas.sucursal = sigla de sucursal o SP-* supervisor).
 */

const REPORTES_SUCURSALES_ESTADOS = [
    'Nueva',
    'En Revisión Banco',
    'Aprobada',
    'Rechazada',
    'Completada',
    'Desistimiento',
];

const REPORTES_SUCURSALES_NOMBRES = [
    'CH' => 'Chiriquí',
    'CV' => 'Costa Verde',
    'TBM' => 'Tumbamuerto',
    'VIS' => 'Vía Israel',
    'BDC' => 'BDC',
    'NN' => 'Supervisor nacional',
];

/** @return array<string, int> */
function reportes_sucursales_estados_vacios(): array
{
    $out = [];
    foreach (REPORTES_SUCURSALES_ESTADOS as $e) {
        $out[$e] = 0;
    }
    $out['total'] = 0;
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
function reportes_sucursales_obtener_datos(PDO $pdo, ?int $anio = null): array
{
    $anio = $anio ?: (int) date('Y');
    $estadosVacios = reportes_sucursales_estados_vacios();

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

    $solicitudes = $pdo->query("
        SELECT s.id, s.estado, s.fecha_creacion, s.ejecutivo_ventas_id
        FROM solicitudes_credito s
    ")->fetchAll(PDO::FETCH_ASSOC);

    $porSucursal = [];
    $porAgente = [];
    $porSupervisor = [];
    $porEstado = array_fill_keys(REPORTES_SUCURSALES_ESTADOS, 0);
    $sinEjecutivo = 0;
    $totalAnio = 0;
    $serieMensual = [];

    foreach (array_keys(REPORTES_SUCURSALES_NOMBRES) as $cod) {
        if ($cod === 'NN') {
            continue;
        }
        $porSucursal[$cod] = array_merge(
            ['codigo' => $cod, 'nombre' => REPORTES_SUCURSALES_NOMBRES[$cod], 'siglas_agentes' => $cod],
            $estadosVacios
        );
        $serieMensual[$cod] = array_fill(1, 12, 0);
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
        $anioSol = $fecha ? (int) date('Y', strtotime($fecha)) : 0;
        $mes = $fecha ? (int) date('n', strtotime($fecha)) : 0;

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
                if ($anioSol === $anio && $mes >= 1 && $mes <= 12) {
                    $serieMensual[$codigo][$mes]++;
                    $totalAnio++;
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
            foreach ($porSucursal as $cod => $row) {
                foreach (REPORTES_SUCURSALES_ESTADOS as $e) {
                    $bucket[$e] += $row[$e];
                }
            }
            $bucket['total'] = array_sum(array_intersect_key($bucket, array_flip(REPORTES_SUCURSALES_ESTADOS)));
        } elseif ($codigo !== '' && isset($porSucursal[$codigo])) {
            foreach (REPORTES_SUCURSALES_ESTADOS as $e) {
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
    $cerradas = $totalSolicitudes - ($porEstado['Nueva'] ?? 0);
    $tasaAprobacion = $cerradas > 0 ? round(100 * $aprobadas / $cerradas, 1) : 0;

    $mesesLabels = [];
    $seriesLinea = [];
    for ($m = 1; $m <= 12; $m++) {
        $mesesLabels[] = date('M', mktime(0, 0, 0, $m, 1));
    }
    foreach ($serieMensual as $cod => $meses) {
        if ($cod === 'NN') {
            continue;
        }
        $seriesLinea[] = [
            'codigo' => $cod,
            'nombre' => REPORTES_SUCURSALES_NOMBRES[$cod] ?? $cod,
            'datos' => array_values($meses),
        ];
    }

    $topAgentes = array_slice($porAgente, 0, 12);

    return [
        'anio' => $anio,
        'catalogo_sucursales' => REPORTES_SUCURSALES_NOMBRES,
        'kpis' => [
            'total_solicitudes' => $totalSolicitudes,
            'total_anio' => $totalAnio,
            'sin_ejecutivo' => $sinEjecutivo,
            'total_agentes' => count(array_filter($mapEjecutivo, static fn($e) => $e['tipo'] === 'agente')),
            'total_supervisores' => count($porSupervisor),
            'tasa_aprobacion' => $tasaAprobacion,
            'sucursal_lider' => $lider ? [
                'codigo' => $lider['codigo'],
                'nombre' => $lider['nombre'],
                'total' => $lider['total'],
            ] : null,
        ],
        'por_estado' => array_map(static fn($e) => ['estado' => $e, 'total' => $porEstado[$e]], REPORTES_SUCURSALES_ESTADOS),
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
