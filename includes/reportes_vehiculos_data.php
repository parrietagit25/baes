<?php
/**
 * Agregaciones para reporte admin: vehículo en solicitudes de crédito.
 * Prioriza el primer registro de vehiculos_solicitud (MIN(id)); si no hay fila, usa cabecera solicitudes_credito.
 */

declare(strict_types=1);

function rep_veh_tabla_existe(PDO $pdo, string $nombre): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$nombre]);

    return (int) $st->fetchColumn() > 0;
}

function rep_veh_parse_filtros(): array
{
    $desde = isset($_GET['desde']) ? trim((string) $_GET['desde']) : '';
    $hasta = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : '';
    $estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
    $valid = ['Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada', 'Desistimiento'];
    if ($estado !== '' && !in_array($estado, $valid, true)) {
        $estado = '';
    }
    if ($desde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $desde = (new DateTimeImmutable('-365 days'))->format('Y-m-d');
    }
    if ($hasta === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $hasta = (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    return ['desde' => $desde, 'hasta' => $hasta, 'estado' => $estado];
}

/**
 * @return array<string, mixed>
 */
function rep_veh_normalize_row(array $row): array
{
    $marca = trim((string) ($row['v_marca'] ?? ''));
    if ($marca === '') {
        $marca = trim((string) ($row['marca_auto'] ?? ''));
    }
    $modelo = trim((string) ($row['v_modelo'] ?? ''));
    if ($modelo === '') {
        $modelo = trim((string) ($row['modelo_auto'] ?? ''));
    }
    $marcaDisp = $marca !== '' ? $marca : 'Sin marca';
    $modeloDisp = $modelo !== '' ? $modelo : 'Sin modelo';

    $anio = null;
    if (isset($row['v_anio']) && $row['v_anio'] !== null && $row['v_anio'] !== '') {
        $anio = (int) $row['v_anio'];
    } elseif (isset($row['y_auto']) && $row['y_auto'] !== null && $row['y_auto'] !== '') {
        $anio = (int) $row['y_auto'];
    }

    $km = null;
    if (isset($row['v_km']) && $row['v_km'] !== null && $row['v_km'] !== '') {
        $km = (int) $row['v_km'];
    } elseif (isset($row['s_km']) && $row['s_km'] !== null && $row['s_km'] !== '') {
        $km = (int) $row['s_km'];
    }

    $precio = null;
    if (isset($row['v_precio']) && $row['v_precio'] !== null && $row['v_precio'] !== '') {
        $precio = (float) $row['v_precio'];
    } elseif (isset($row['precio_especial']) && $row['precio_especial'] !== null && $row['precio_especial'] !== '') {
        $precio = (float) $row['precio_especial'];
    }

    $abonoPct = null;
    if (isset($row['v_abono_pct']) && $row['v_abono_pct'] !== null && $row['v_abono_pct'] !== '') {
        $abonoPct = (float) $row['v_abono_pct'];
    } elseif (isset($row['abono_porcentaje']) && $row['abono_porcentaje'] !== null && $row['abono_porcentaje'] !== '') {
        $abonoPct = (float) $row['abono_porcentaje'];
    }

    $abonoMonto = null;
    if (isset($row['v_abono_monto']) && $row['v_abono_monto'] !== null && $row['v_abono_monto'] !== '') {
        $abonoMonto = (float) $row['v_abono_monto'];
    } elseif (isset($row['abono_monto']) && $row['abono_monto'] !== null && $row['abono_monto'] !== '') {
        $abonoMonto = (float) $row['abono_monto'];
    }

    $ingreso = null;
    if (isset($row['ingreso']) && $row['ingreso'] !== null && $row['ingreso'] !== '') {
        $ingreso = (float) $row['ingreso'];
    }

    $edad = null;
    if (isset($row['edad']) && $row['edad'] !== null && $row['edad'] !== '') {
        $edad = (int) $row['edad'];
    }

    $labelModelo = $marcaDisp !== 'Sin marca' || $modeloDisp !== 'Sin modelo'
        ? ($marcaDisp . ' / ' . $modeloDisp)
        : 'Sin modelo';

    return [
        'id' => (int) ($row['id'] ?? 0),
        'fecha_creacion' => (string) ($row['fecha_creacion'] ?? ''),
        'nombre_cliente' => (string) ($row['nombre_cliente'] ?? ''),
        'estado' => (string) ($row['estado'] ?? ''),
        'marca' => $marcaDisp,
        'modelo' => $modeloDisp,
        'label_modelo' => $labelModelo,
        'anio' => $anio,
        'km' => $km,
        'precio' => $precio,
        'abono_pct' => $abonoPct,
        'abono_monto' => $abonoMonto,
        'ingreso' => $ingreso,
        'edad' => $edad,
        'perfil_financiero' => (string) ($row['perfil_financiero'] ?? ''),
        'genero' => (string) ($row['genero'] ?? ''),
        'tiene_detalle_vehiculo' => trim((string) ($row['v_marca'] ?? '')) !== '' || trim((string) ($row['v_modelo'] ?? '')) !== '',
    ];
}

/**
 * @param array<int, float|int> $nums
 */
function rep_veh_median_numeric(array $nums): ?float
{
    $nums = array_values(array_filter($nums, static function ($x) {
        return $x !== null && is_numeric($x);
    }));
    $c = count($nums);
    if ($c === 0) {
        return null;
    }
    sort($nums);
    $mid = intdiv($c, 2);
    if ($c % 2 === 1) {
        return (float) $nums[$mid];
    }

    return ((float) $nums[$mid - 1] + (float) $nums[$mid]) / 2.0;
}

function rep_veh_rango_edad(?int $edad): string
{
    if ($edad === null || $edad < 1) {
        return 'Sin dato';
    }
    if ($edad < 30) {
        return '18-29';
    }
    if ($edad < 40) {
        return '30-39';
    }
    if ($edad < 50) {
        return '40-49';
    }
    if ($edad < 60) {
        return '50-59';
    }

    return '60+';
}

function rep_veh_rango_km(?int $km): string
{
    if ($km === null || $km < 0) {
        return 'Sin dato';
    }
    if ($km < 30000) {
        return '0-29.999';
    }
    if ($km < 60000) {
        return '30k-59.999';
    }
    if ($km < 100000) {
        return '60k-99.999';
    }
    if ($km < 150000) {
        return '100k-149.999';
    }

    return '150k+';
}

function rep_veh_rango_precio(?float $precio): string
{
    if ($precio === null || $precio <= 0) {
        return 'Sin dato';
    }
    if ($precio < 10000) {
        return '< 10k';
    }
    if ($precio < 20000) {
        return '10k-19.999';
    }
    if ($precio < 30000) {
        return '20k-29.999';
    }
    if ($precio < 50000) {
        return '30k-49.999';
    }

    return '50k+';
}

function rep_veh_rango_abono(?float $pct): string
{
    if ($pct === null || $pct < 0) {
        return 'Sin dato';
    }
    if ($pct < 5) {
        return '< 5%';
    }
    if ($pct < 10) {
        return '5-9%';
    }
    if ($pct < 20) {
        return '10-19%';
    }
    if ($pct < 30) {
        return '20-29%';
    }

    return '30%+';
}

/**
 * @return array<int, array<string, mixed>>
 */
function rep_veh_fetch_raw(PDO $pdo, array $filt, bool $joinVehiculos): array
{
    $desde = $filt['desde'];
    $hasta = $filt['hasta'];
    $estado = $filt['estado'];

    if ($joinVehiculos) {
        $sql = '
            SELECT
                s.id, s.nombre_cliente, s.estado, s.fecha_creacion,
                s.edad, s.ingreso, s.genero, s.perfil_financiero,
                s.marca_auto, s.modelo_auto, s.`año_auto` AS y_auto, s.kilometraje AS s_km,
                s.precio_especial, s.abono_porcentaje, s.abono_monto,
                v.marca AS v_marca, v.modelo AS v_modelo, v.anio AS v_anio, v.kilometraje AS v_km,
                v.precio AS v_precio, v.abono_porcentaje AS v_abono_pct, v.abono_monto AS v_abono_monto
            FROM solicitudes_credito s
            LEFT JOIN (
                SELECT v1.*
                FROM vehiculos_solicitud v1
                INNER JOIN (
                    SELECT solicitud_id, MIN(id) AS min_id
                    FROM vehiculos_solicitud
                    GROUP BY solicitud_id
                ) t ON v1.id = t.min_id
            ) v ON v.solicitud_id = s.id
            WHERE DATE(s.fecha_creacion) BETWEEN :d1 AND :d2
        ';
    } else {
        $sql = '
            SELECT
                s.id, s.nombre_cliente, s.estado, s.fecha_creacion,
                s.edad, s.ingreso, s.genero, s.perfil_financiero,
                s.marca_auto, s.modelo_auto, s.`año_auto` AS y_auto, s.kilometraje AS s_km,
                s.precio_especial, s.abono_porcentaje, s.abono_monto,
                NULL AS v_marca, NULL AS v_modelo, NULL AS v_anio, NULL AS v_km,
                NULL AS v_precio, NULL AS v_abono_pct, NULL AS v_abono_monto
            FROM solicitudes_credito s
            WHERE DATE(s.fecha_creacion) BETWEEN :d1 AND :d2
        ';
    }

    if ($estado !== '') {
        $sql .= ' AND s.estado = :estado ';
    }
    $sql .= ' ORDER BY s.fecha_creacion DESC LIMIT 8000';

    $st = $pdo->prepare($sql);
    $st->bindValue(':d1', $desde);
    $st->bindValue(':d2', $hasta);
    if ($estado !== '') {
        $st->bindValue(':estado', $estado);
    }
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array<int, array<string, mixed>> $normalized
 * @return array<int, array{label: string, n: int}>
 */
function rep_veh_top_modelos(array $normalized, int $limit): array
{
    $counts = [];
    foreach ($normalized as $n) {
        $lab = (string) ($n['label_modelo'] ?? '');
        if ($lab === '') {
            continue;
        }
        $counts[$lab] = ($counts[$lab] ?? 0) + 1;
    }
    arsort($counts);
    $out = [];
    foreach ($counts as $label => $n) {
        $out[] = ['label' => $label, 'n' => (int) $n];
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * @return array<string, mixed>
 */
function rep_veh_build_payload(PDO $pdo, array $filt): array
{
    $joinVeh = rep_veh_tabla_existe($pdo, 'vehiculos_solicitud');
    $raw = rep_veh_fetch_raw($pdo, $filt, $joinVeh);

    $normalized = [];
    foreach ($raw as $row) {
        $normalized[] = rep_veh_normalize_row($row);
    }

    $n = count($normalized);
    $precios = [];
    $ingresos = [];
    $abonos = [];
    $kms = [];
    $edades = [];
    $anios = [];
    $ratios = [];

    $porMarca = [];
    $porEstado = [];
    $porAnio = [];
    $porRangoEdad = [];
    $porRangoKm = [];
    $porRangoPrecio = [];
    $porRangoAbono = [];

    $sumPrecioMarca = [];
    $cntPrecioMarca = [];

    $nConVehiculoInfo = 0;

    foreach ($normalized as $nr) {
        $m = (string) ($nr['marca'] ?? 'Sin marca');
        $porMarca[$m] = ($porMarca[$m] ?? 0) + 1;

        $est = (string) ($nr['estado'] ?? 'Sin dato');
        $porEstado[$est] = ($porEstado[$est] ?? 0) + 1;

        if (($nr['marca'] ?? '') !== 'Sin marca' || ($nr['modelo'] ?? '') !== 'Sin modelo') {
            $nConVehiculoInfo++;
        }

        $anio = $nr['anio'] ?? null;
        if ($anio !== null && $anio > 1900 && $anio <= (int) date('Y') + 1) {
            $ak = (string) $anio;
            $porAnio[$ak] = ($porAnio[$ak] ?? 0) + 1;
            $anios[] = $anio;
        }

        $edad = $nr['edad'] ?? null;
        $rEd = rep_veh_rango_edad($edad);
        $porRangoEdad[$rEd] = ($porRangoEdad[$rEd] ?? 0) + 1;
        if ($edad !== null && $edad > 0) {
            $edades[] = $edad;
        }

        $km = $nr['km'] ?? null;
        $rKm = rep_veh_rango_km($km);
        $porRangoKm[$rKm] = ($porRangoKm[$rKm] ?? 0) + 1;
        if ($km !== null && $km >= 0) {
            $kms[] = $km;
        }

        $precio = $nr['precio'] ?? null;
        $rPr = rep_veh_rango_precio($precio);
        $porRangoPrecio[$rPr] = ($porRangoPrecio[$rPr] ?? 0) + 1;
        if ($precio !== null && $precio > 0) {
            $precios[] = $precio;
            if ($m !== 'Sin marca') {
                $sumPrecioMarca[$m] = ($sumPrecioMarca[$m] ?? 0.0) + $precio;
                $cntPrecioMarca[$m] = ($cntPrecioMarca[$m] ?? 0) + 1;
            }
        }

        $ap = $nr['abono_pct'] ?? null;
        $rAb = rep_veh_rango_abono($ap);
        $porRangoAbono[$rAb] = ($porRangoAbono[$rAb] ?? 0) + 1;
        if ($ap !== null && $ap >= 0) {
            $abonos[] = $ap;
        }

        $ing = $nr['ingreso'] ?? null;
        if ($ing !== null && $ing > 0) {
            $ingresos[] = $ing;
        }
        if ($precio !== null && $precio > 0 && $ing !== null && $ing > 0) {
            $ratios[] = $precio / $ing;
        }
    }

    ksort($porAnio, SORT_NUMERIC);

    $precioPromMarca = [];
    foreach ($sumPrecioMarca as $marca => $sum) {
        $c = $cntPrecioMarca[$marca] ?? 0;
        if ($c > 0) {
            $precioPromMarca[$marca] = round($sum / $c, 2);
        }
    }
    arsort($precioPromMarca);

    $medianAnio = rep_veh_median_numeric($anios);
    $medianAnioInt = $medianAnio !== null ? (int) round($medianAnio) : null;

    $avg = static function (array $xs): ?float {
        if ($xs === []) {
            return null;
        }

        return round(array_sum($xs) / count($xs), 2);
    };

    $ordenRangosEdad = ['18-29', '30-39', '40-49', '50-59', '60+', 'Sin dato'];
    $ordenRangosKm = ['0-29.999', '30k-59.999', '60k-99.999', '100k-149.999', '150k+', 'Sin dato'];
    $ordenRangosPrecio = ['< 10k', '10k-19.999', '20k-29.999', '30k-49.999', '50k+', 'Sin dato'];
    $ordenRangosAbono = ['< 5%', '5-9%', '10-19%', '20-29%', '30%+', 'Sin dato'];

    $muestra = array_slice($normalized, 0, 150);

    return [
        'success' => true,
        'filtros' => $filt,
        'fuente_vehiculo' => $joinVeh ? 'vehiculos_solicitud (primer id) + cabecera' : 'solo cabecera solicitudes_credito',
        'kpi' => [
            'n' => $n,
            'n_con_marca_modelo' => $nConVehiculoInfo,
            'precio_promedio' => $avg($precios),
            'ingreso_promedio' => $avg($ingresos),
            'abono_pct_promedio' => $avg($abonos),
            'km_promedio' => $avg($kms),
            'edad_promedio' => $avg(array_map(static function ($x) {
                return (float) $x;
            }, $edades)),
            'anio_vehiculo_mediano' => $medianAnioInt,
            'ratio_precio_ingreso_mediano' => rep_veh_median_numeric($ratios),
        ],
        'por_marca' => $porMarca,
        'por_modelo_top' => rep_veh_top_modelos($normalized, 18),
        'por_anio' => $porAnio,
        'por_estado' => $porEstado,
        'orden_rangos_edad' => $ordenRangosEdad,
        'por_rango_edad' => $porRangoEdad,
        'orden_rangos_km' => $ordenRangosKm,
        'por_rango_km' => $porRangoKm,
        'orden_rangos_precio' => $ordenRangosPrecio,
        'por_rango_precio' => $porRangoPrecio,
        'orden_rangos_abono' => $ordenRangosAbono,
        'por_rango_abono_pct' => $porRangoAbono,
        'precio_promedio_por_marca' => array_slice($precioPromMarca, 0, 15, true),
        'muestra' => $muestra,
    ];
}

/**
 * @return array<string, mixed>
 */
function rep_veh_build_json(PDO $pdo): array
{
    $filt = rep_veh_parse_filtros();

    return rep_veh_build_payload($pdo, $filt);
}

/**
 * @return array<int, array<int, string|float|int>>
 */
function rep_veh_filas_export(PDO $pdo, array $filt): array
{
    $joinVeh = rep_veh_tabla_existe($pdo, 'vehiculos_solicitud');
    $raw = rep_veh_fetch_raw($pdo, $filt, $joinVeh);
    $out = [];
    foreach ($raw as $row) {
        $n = rep_veh_normalize_row($row);
        $out[] = [
            $n['id'],
            $n['fecha_creacion'],
            $n['nombre_cliente'],
            $n['estado'],
            $n['marca'],
            $n['modelo'],
            $n['anio'] ?? '',
            $n['km'] ?? '',
            $n['precio'] ?? '',
            $n['abono_pct'] ?? '',
            $n['abono_monto'] ?? '',
            $n['ingreso'] ?? '',
            $n['edad'] ?? '',
            $n['perfil_financiero'],
            $n['genero'],
            $n['tiene_detalle_vehiculo'] ? 'Sí' : 'No',
        ];
    }

    return $out;
}
