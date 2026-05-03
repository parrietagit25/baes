<?php
/**
 * Agregaciones y heurísticas para reportes de Sol. Financiamiento (financiamiento_registros).
 * Perfil laboral y sector público/privado son estimaciones por texto; no sustituyen validación manual.
 */

declare(strict_types=1);

function rep_fin_tabla_existe(PDO $pdo, string $nombre): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$nombre]);

    return (int) $st->fetchColumn() > 0;
}

function rep_fin_columna_existe(PDO $pdo, string $tabla, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$tabla, $col]);

    return (int) $st->fetchColumn() > 0;
}

function rep_fin_blob_inferencia(array $r): string
{
    $parts = [
        (string) ($r['empresa_ocupacion'] ?? ''),
        (string) ($r['otros_ingresos'] ?? ''),
        (string) ($r['ocupacion_otros'] ?? ''),
        (string) ($r['empresa_nombre'] ?? ''),
        (string) ($r['trabajo_anterior'] ?? ''),
        (string) ($r['empresa_direccion'] ?? ''),
        (string) ($r['barriada_calle_casa'] ?? ''),
        (string) ($r['prov_dist_corr'] ?? ''),
    ];

    return mb_strtolower(trim(implode(' ', $parts)), 'UTF-8');
}

function rep_fin_clasificar_perfil(array $r): string
{
    $blob = rep_fin_blob_inferencia($r);
    if (preg_match('/\b(jubilad|jubil|pensi[oó]n|pensionad|retirad|invalidez)\b/u', $blob)) {
        return 'Jubilado (estimado)';
    }
    if (preg_match('/\b(independ|freelance|aut[oó]nom|negocio propio|por cuenta propia|emprendedor)\b/u', $blob)) {
        return 'Independiente (estimado)';
    }

    return 'Asalariado (por defecto)';
}

function rep_fin_clasificar_sector(array $r, string $perfilEstimado): string
{
    if (!str_contains($perfilEstimado, 'Asalariado')) {
        return 'N/A (no asalariado estimado)';
    }
    $blob = rep_fin_blob_inferencia($r);
    if (preg_match(
        '/\b(minsa|css|caja de seguro|caja del seguro|seguro social|gobierno|ministerio|mitradel|asamblea|municipal|municipio|presidencia|sinaproc|anati|senan|polic[ií]a nacional|fiscal[ií]a|tribunal|juzgado|miviot|meduca|mef|contralor[ií]a|embajada)\b/u',
        $blob
    )) {
        return 'Gobierno / público (estimado)';
    }

    return 'Privado (estimado)';
}

function rep_fin_label_genero(?string $sexo): string
{
    $s = strtoupper(trim((string) $sexo));
    if ($s === 'F') {
        return 'Femenino';
    }
    if ($s === 'M') {
        return 'Masculino';
    }
    if ($s === '') {
        return 'Sin dato';
    }

    return 'Otro';
}

function rep_fin_edad_calculada(array $r): ?int
{
    if (isset($r['cliente_edad']) && is_numeric($r['cliente_edad'])) {
        $e = (int) $r['cliente_edad'];
        if ($e > 0 && $e < 130) {
            return $e;
        }
    }
    $nac = $r['cliente_nacimiento'] ?? null;
    if ($nac && (string) $nac !== '' && (string) $nac !== '0000-00-00') {
        try {
            $d0 = new DateTimeImmutable((string) $nac);
            $d1 = new DateTimeImmutable('today');
            $y = $d0->diff($d1)->y;
            if ($y > 0 && $y < 130) {
                return $y;
            }
        } catch (Throwable $e) {
        }
    }

    return null;
}

function rep_fin_rango_edad(?int $e): string
{
    if ($e === null || $e < 1) {
        return 'Sin edad';
    }
    if ($e < 18) {
        return 'Menor de 18';
    }
    if ($e <= 29) {
        return '18 – 29';
    }
    if ($e <= 39) {
        return '30 – 39';
    }
    if ($e <= 49) {
        return '40 – 49';
    }
    if ($e <= 59) {
        return '50 – 59';
    }

    return '60 o más';
}

/** @param mixed $sal */
function rep_fin_rango_salario($sal): string
{
    if ($sal === null || $sal === '') {
        return 'Sin salario declarado';
    }
    $v = (float) $sal;
    if ($v <= 0) {
        return 'Sin salario declarado';
    }
    if ($v <= 1500) {
        return 'USD 0 – 1 500';
    }
    if ($v <= 2500) {
        return 'USD 1 501 – 2 500';
    }
    if ($v <= 4000) {
        return 'USD 2 501 – 4 000';
    }
    if ($v <= 6000) {
        return 'USD 4 001 – 6 000';
    }

    return 'USD más de 6 000';
}

/** @return array{0:string,1:string} Y-m-d */
function rep_fin_rango_fechas_efectivo(string $desde, string $hasta): array
{
    try {
        $h = $hasta !== '' ? new DateTimeImmutable($hasta) : new DateTimeImmutable('today');
    } catch (Throwable $e) {
        $h = new DateTimeImmutable('today');
    }
    try {
        $d = $desde !== '' ? new DateTimeImmutable($desde) : $h->modify('-365 days');
    } catch (Throwable $e) {
        $d = $h->modify('-365 days');
    }
    if ($d > $h) {
        $tmp = $d;
        $d = $h;
        $h = $tmp;
    }

    return [$d->format('Y-m-d'), $h->format('Y-m-d')];
}

/** @return array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string,estado_sc:string} */
function rep_fin_parse_filtros(): array
{
    $desde = isset($_GET['desde']) ? trim((string) $_GET['desde']) : '';
    $hasta = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : '';
    $generosRaw = isset($_GET['generos']) ? trim((string) $_GET['generos']) : '';
    $generos = [];
    if ($generosRaw !== '') {
        foreach (explode(',', $generosRaw) as $g) {
            $g = trim($g);
            if ($g !== '') {
                $generos[] = $g;
            }
        }
    }
    $perfil = isset($_GET['perfil']) ? trim((string) $_GET['perfil']) : '';
    if (!in_array($perfil, ['', 'jubilado', 'independiente', 'asalariado'], true)) {
        $perfil = '';
    }
    $sector = isset($_GET['sector']) ? trim((string) $_GET['sector']) : '';
    if (!in_array($sector, ['', 'gobierno', 'privado'], true)) {
        $sector = '';
    }
    $estadoSc = isset($_GET['estado_sc']) ? trim((string) $_GET['estado_sc']) : '';

    return [
        'desde' => $desde,
        'hasta' => $hasta,
        'generos' => $generos,
        'perfil' => $perfil,
        'sector' => $sector,
        'estado_sc' => $estadoSc,
    ];
}

function rep_fin_tipo_perfil_desde_etiqueta(string $perfilEstimado): string
{
    if (str_contains($perfilEstimado, 'Jubilado')) {
        return 'Jubilado';
    }
    if (str_contains($perfilEstimado, 'Independiente')) {
        return 'Independiente';
    }

    return 'Asalariado';
}

/**
 * @param array<string,mixed> $r
 * @return array<string,mixed>
 */
function rep_fin_enriquecer_fila_publica(array $r): array
{
    $perfil = rep_fin_clasificar_perfil($r);
    $r['genero_label'] = rep_fin_label_genero(isset($r['cliente_sexo']) ? (string) $r['cliente_sexo'] : null);
    $r['edad_calculada'] = rep_fin_edad_calculada($r);
    $r['rango_edad'] = rep_fin_rango_edad($r['edad_calculada']);
    $r['rango_salario_usd'] = rep_fin_rango_salario($r['empresa_salario'] ?? null);
    $r['perfil_estimado'] = $perfil;
    $r['sector_estimado'] = rep_fin_clasificar_sector($r, $perfil);

    return $r;
}

/**
 * @param array<string,mixed> $r
 * @param array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string,estado_sc:string} $filt
 */
function rep_fin_pasar_filtro(array $r, array $filt, bool $conSolicitud): bool
{
    if ($conSolicitud && $filt['estado_sc'] !== '') {
        $est = (string) ($r['solicitud_estado'] ?? '');
        if ($est !== $filt['estado_sc']) {
            return false;
        }
    }
    if ($filt['generos'] !== []) {
        if (!in_array((string) ($r['genero_label'] ?? ''), $filt['generos'], true)) {
            return false;
        }
    }
    if ($filt['perfil'] !== '') {
        $p = (string) ($r['perfil_estimado'] ?? '');
        if ($filt['perfil'] === 'jubilado' && !str_contains($p, 'Jubilado')) {
            return false;
        }
        if ($filt['perfil'] === 'independiente' && !str_contains($p, 'Independiente')) {
            return false;
        }
        if ($filt['perfil'] === 'asalariado' && !str_contains($p, 'Asalariado')) {
            return false;
        }
    }
    if ($filt['sector'] !== '') {
        $s = (string) ($r['sector_estimado'] ?? '');
        if ($filt['sector'] === 'gobierno' && !str_contains($s, 'Gobierno')) {
            return false;
        }
        if ($filt['sector'] === 'privado' && !str_contains($s, 'Privado')) {
            return false;
        }
    }

    return true;
}

/** @return array<int,string> */
function rep_fin_orden_rangos_salario(): array
{
    return [
        'Sin salario declarado',
        'USD 0 – 1 500',
        'USD 1 501 – 2 500',
        'USD 2 501 – 4 000',
        'USD 4 001 – 6 000',
        'USD más de 6 000',
    ];
}

/** @return array<int,string> */
function rep_fin_orden_rangos_edad(): array
{
    return [
        'Sin edad',
        'Menor de 18',
        '18 – 29',
        '30 – 39',
        '40 – 49',
        '50 – 59',
        '60 o más',
    ];
}

/**
 * @param array<int,array<string,mixed>> $filas
 * @return array<string,mixed>
 */
function rep_fin_agregar_distribuciones(array $filas): array
{
    $ordenSal = rep_fin_orden_rangos_salario();
    $ordenEdad = rep_fin_orden_rangos_edad();
    $ordenGen = ['Femenino', 'Masculino', 'Otro', 'Sin dato'];

    $porGenero = [];
    $porSalario = [];
    $porEdad = [];
    $porPerfil = [];
    $porSectorAsalariados = [];
    $cruceSalGen = [];
    foreach ($ordenSal as $rs) {
        $cruceSalGen[$rs] = [];
        foreach ($ordenGen as $g) {
            $cruceSalGen[$rs][$g] = 0;
        }
    }

    $sumEdad = 0;
    $nEdad = 0;
    $sumSal = 0.0;
    $nSal = 0;

    foreach ($filas as $r) {
        $g = (string) ($r['genero_label'] ?? 'Sin dato');
        $porGenero[$g] = ($porGenero[$g] ?? 0) + 1;

        $rs = (string) ($r['rango_salario_usd'] ?? 'Sin salario declarado');
        $porSalario[$rs] = ($porSalario[$rs] ?? 0) + 1;

        $re = (string) ($r['rango_edad'] ?? 'Sin edad');
        $porEdad[$re] = ($porEdad[$re] ?? 0) + 1;

        $pe = (string) ($r['perfil_estimado'] ?? '');
        $porPerfil[$pe] = ($porPerfil[$pe] ?? 0) + 1;

        $sec = (string) ($r['sector_estimado'] ?? '');
        if (str_contains((string) ($r['perfil_estimado'] ?? ''), 'Asalariado') && (str_contains($sec, 'Gobierno') || str_contains($sec, 'Privado'))) {
            $porSectorAsalariados[$sec] = ($porSectorAsalariados[$sec] ?? 0) + 1;
        }

        if (!isset($cruceSalGen[$rs])) {
            $cruceSalGen[$rs] = array_fill_keys($ordenGen, 0);
        }
        if (!isset($cruceSalGen[$rs][$g])) {
            $cruceSalGen[$rs][$g] = 0;
        }
        $cruceSalGen[$rs][$g]++;

        $ed = $r['edad_calculada'] ?? null;
        if (is_int($ed) && $ed > 0) {
            $sumEdad += $ed;
            $nEdad++;
        }
        $sal = $r['empresa_salario'] ?? null;
        if ($sal !== null && is_numeric($sal) && (float) $sal > 0) {
            $sumSal += (float) $sal;
            $nSal++;
        }
    }

    $labelsSal = [];
    foreach ($ordenSal as $lbl) {
        if (($porSalario[$lbl] ?? 0) > 0 || isset($cruceSalGen[$lbl])) {
            $labelsSal[] = $lbl;
        }
    }
    foreach (array_keys($cruceSalGen) as $lbl) {
        if (!in_array($lbl, $labelsSal, true) && array_sum($cruceSalGen[$lbl]) > 0) {
            $labelsSal[] = $lbl;
        }
    }

    $datasetsCruce = [];
    foreach ($ordenGen as $gen) {
        $data = [];
        foreach ($labelsSal as $lbl) {
            $data[] = (int) ($cruceSalGen[$lbl][$gen] ?? 0);
        }
        $datasetsCruce[] = ['label' => $gen, 'data' => $data];
    }

    return [
        'por_genero' => $porGenero,
        'por_rango_salario' => $porSalario,
        'por_rango_edad' => $porEdad,
        'por_perfil_estimado' => $porPerfil,
        'por_sector_asalariado_estimado' => $porSectorAsalariados,
        'cruce_salario_genero' => [
            'labels' => $labelsSal,
            'datasets' => $datasetsCruce,
        ],
        'orden_rangos_salario' => $ordenSal,
        'orden_rangos_edad' => $ordenEdad,
        'orden_generos' => $ordenGen,
        'stats' => [
            'n' => count($filas),
            'edad_promedio' => $nEdad > 0 ? round($sumEdad / $nEdad, 1) : null,
            'salario_promedio_usd' => $nSal > 0 ? round($sumSal / $nSal, 2) : null,
        ],
    ];
}

/**
 * @param array<int,array<string,mixed>> $filas
 * @return array<string,mixed>
 */
function rep_fin_agregar_enlazada_extra(array $filas): array
{
    $porEstado = [];
    $porPerfilMotus = [];
    $cruce = [];
    $coincidePerfil = 0;
    $diffPerfil = 0;
    $coincideGenero = 0;
    $diffGenero = 0;
    $sinGeneroMotus = 0;

    foreach ($filas as $r) {
        $e = (string) ($r['solicitud_estado'] ?? '');
        $porEstado[$e] = ($porEstado[$e] ?? 0) + 1;

        $pm = (string) ($r['perfil_motus'] ?? '');
        if ($pm !== '') {
            $porPerfilMotus[$pm] = ($porPerfilMotus[$pm] ?? 0) + 1;
        }

        if (!isset($cruce[$e])) {
            $cruce[$e] = [];
        }
        if ($pm !== '') {
            $cruce[$e][$pm] = ($cruce[$e][$pm] ?? 0) + 1;
        }

        $tipoFr = rep_fin_tipo_perfil_desde_etiqueta((string) ($r['perfil_estimado'] ?? ''));
        if ($pm !== '') {
            if ($tipoFr === $pm) {
                $coincidePerfil++;
            } else {
                $diffPerfil++;
            }
        }

        $gm = (string) ($r['genero_motus'] ?? '');
        $gf = (string) ($r['genero_label'] ?? '');
        if ($gm === '') {
            $sinGeneroMotus++;
        } else {
            if (rep_fin_generos_equivalentes($gf, $gm)) {
                $coincideGenero++;
            } else {
                $diffGenero++;
            }
        }
    }

    $labelsEstado = array_keys($porEstado);
    sort($labelsEstado);
    $perfilesMotus = ['Asalariado', 'Jubilado', 'Independiente'];
    $matrix = [];
    foreach ($labelsEstado as $est) {
        $row = [];
        foreach ($perfilesMotus as $pf) {
            $row[] = (int) ($cruce[$est][$pf] ?? 0);
        }
        $matrix[] = $row;
    }

    return [
        'por_estado_solicitud' => $porEstado,
        'por_perfil_motus' => $porPerfilMotus,
        'cruce_estado_perfil_motus' => [
            'labels_estado' => $labelsEstado,
            'labels_perfil' => $perfilesMotus,
            'matrix' => $matrix,
        ],
        'comparacion' => [
            'perfil_coincide' => $coincidePerfil,
            'perfil_distinto' => $diffPerfil,
            'genero_coincide' => $coincideGenero,
            'genero_distinto' => $diffGenero,
            'genero_motus_vacio' => $sinGeneroMotus,
        ],
    ];
}

function rep_fin_generos_equivalentes(string $labelFr, string $generoMotus): bool
{
    $map = [
        'Femenino' => 'Femenino',
        'Masculino' => 'Masculino',
        'Otro' => 'Otro',
        'Sin dato' => '',
    ];
    $a = $map[$labelFr] ?? $labelFr;
    if ($a === '' && $generoMotus === '') {
        return true;
    }

    return $a !== '' && strcasecmp($a, $generoMotus) === 0;
}

/**
 * @return array<int,array<string,mixed>>
 */
function rep_fin_fetch_publica(PDO $pdo, string $d1, string $d2): array
{
    $sql = '
        SELECT id, fecha_creacion, cliente_nombre, cliente_sexo, cliente_edad, cliente_nacimiento, empresa_salario,
            empresa_ocupacion, empresa_nombre, otros_ingresos, ocupacion_otros, trabajo_anterior,
            empresa_direccion, barriada_calle_casa, prov_dist_corr
        FROM financiamiento_registros
        WHERE DATE(fecha_creacion) BETWEEN :d1 AND :d2
        ORDER BY fecha_creacion DESC
        LIMIT 15000
    ';
    $st = $pdo->prepare($sql);
    $st->execute(['d1' => $d1, 'd2' => $d2]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{rows:array<int,array<string,mixed>>,error:?string}
 */
function rep_fin_fetch_enlazada(PDO $pdo, string $d1, string $d2): array
{
    if (!rep_fin_columna_existe($pdo, 'solicitudes_credito', 'financiamiento_registro_id')) {
        return [
            'rows' => [],
            'error' => 'Falta la columna solicitudes_credito.financiamiento_registro_id. Ejecute database/migracion_solicitud_financiamiento_registro_id.sql',
        ];
    }
    $sql = '
        SELECT fr.id, fr.fecha_creacion, fr.cliente_nombre, fr.cliente_sexo, fr.cliente_edad, fr.cliente_nacimiento, fr.empresa_salario,
            fr.empresa_ocupacion, fr.empresa_nombre, fr.otros_ingresos, fr.ocupacion_otros, fr.trabajo_anterior,
            fr.empresa_direccion, fr.barriada_calle_casa, fr.prov_dist_corr,
            sc.id AS solicitud_id, sc.estado AS solicitud_estado, sc.perfil_financiero AS perfil_motus,
            sc.ingreso AS ingreso_motus, sc.genero AS genero_motus, sc.edad AS edad_motus,
            sc.nombre_cliente AS nombre_motus, sc.cedula AS cedula_motus
        FROM financiamiento_registros fr
        INNER JOIN solicitudes_credito sc ON sc.financiamiento_registro_id = fr.id
        WHERE DATE(fr.fecha_creacion) BETWEEN :d1 AND :d2
        ORDER BY fr.fecha_creacion DESC
        LIMIT 15000
    ';
    $st = $pdo->prepare($sql);
    $st->execute(['d1' => $d1, 'd2' => $d2]);

    return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'error' => null];
}

/**
 * @param array<string,mixed> $r
 * @return array<string,mixed>
 */
function rep_fin_enriquecer_fila_enlazada(array $r): array
{
    $r = rep_fin_enriquecer_fila_publica($r);
    $tipoFr = rep_fin_tipo_perfil_desde_etiqueta((string) ($r['perfil_estimado'] ?? ''));
    $pm = (string) ($r['perfil_motus'] ?? '');
    $r['perfil_motus_coincide'] = ($pm !== '' && $tipoFr === $pm);
    $r['genero_motus_coincide'] = rep_fin_generos_equivalentes((string) ($r['genero_label'] ?? ''), (string) ($r['genero_motus'] ?? ''));

    return $r;
}

/**
 * @return array<string,mixed>
 */
function rep_fin_build_reporte_publica(PDO $pdo): array
{
    if (!rep_fin_tabla_existe($pdo, 'financiamiento_registros')) {
        return ['success' => false, 'message' => 'No existe la tabla financiamiento_registros en esta base de datos.'];
    }
    $filt = rep_fin_parse_filtros();
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);
    try {
        $raw = rep_fin_fetch_publica($pdo, $d1, $d2);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1146) {
            return ['success' => false, 'message' => 'No existe la tabla financiamiento_registros.'];
        }
        throw $e;
    }

    $filas = [];
    foreach ($raw as $row) {
        $e = rep_fin_enriquecer_fila_publica($row);
        if (rep_fin_pasar_filtro($e, $filt, false)) {
            $filas[] = $e;
        }
    }

    $agg = rep_fin_agregar_distribuciones($filas);
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
        ];
    }

    return array_merge([
        'success' => true,
        'filtros' => array_merge($filt, ['fecha_desde' => $d1, 'fecha_hasta' => $d2]),
        'nota_metodologica' => 'Perfil laboral (asalariado / independiente / jubilado) y sector (público / privado) se infieren por palabras clave en ocupación, empresa y dirección; son aproximaciones.',
        'muestra' => $muestra,
    ], $agg);
}

/**
 * @return array<string,mixed>
 */
function rep_fin_build_reporte_enlazada(PDO $pdo): array
{
    if (!rep_fin_tabla_existe($pdo, 'financiamiento_registros')) {
        return ['success' => false, 'message' => 'No existe la tabla financiamiento_registros en esta base de datos.'];
    }
    $filt = rep_fin_parse_filtros();
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);
    $pack = rep_fin_fetch_enlazada($pdo, $d1, $d2);
    if ($pack['error'] !== null) {
        return ['success' => false, 'message' => $pack['error']];
    }
    $raw = $pack['rows'];

    $filas = [];
    foreach ($raw as $row) {
        $e = rep_fin_enriquecer_fila_enlazada($row);
        if (rep_fin_pasar_filtro($e, $filt, true)) {
            $filas[] = $e;
        }
    }

    $agg = rep_fin_agregar_distribuciones($filas);
    $extra = rep_fin_agregar_enlazada_extra($filas);

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
            'ingreso_motus' => $m['ingreso_motus'] ?? null,
            'genero_motus' => $m['genero_motus'] ?? null,
            'edad_motus' => $m['edad_motus'] ?? null,
            'perfil_motus_coincide' => $m['perfil_motus_coincide'] ?? false,
            'genero_motus_coincide' => $m['genero_motus_coincide'] ?? false,
        ];
    }

    return array_merge([
        'success' => true,
        'filtros' => array_merge($filt, ['fecha_desde' => $d1, 'fecha_hasta' => $d2]),
        'nota_metodologica' => 'Solo registros con solicitud Motus vinculada (financiamiento_registro_id). Las estimaciones del formulario público se comparan con perfil/género declarados en la solicitud de crédito.',
        'muestra' => $muestra,
    ], $agg, ['enlazada' => $extra]);
}

/**
 * @param array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string,estado_sc:string} $filt
 * @return array<int,array<int,string|int|float|bool|null>>
 */
function rep_fin_filas_export_publica(PDO $pdo, array $filt): array
{
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);
    $raw = rep_fin_fetch_publica($pdo, $d1, $d2);
    $out = [];
    foreach ($raw as $row) {
        $e = rep_fin_enriquecer_fila_publica($row);
        if (!rep_fin_pasar_filtro($e, $filt, false)) {
            continue;
        }
        $out[] = [
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
        ];
    }

    return $out;
}

/**
 * @param array{desde:string,hasta:string,generos:array<int,string>,perfil:string,sector:string,estado_sc:string} $filt
 * @return array{array<int,string>,array<int,array<int,string|int|float|bool|null>>}
 */
function rep_fin_filas_export_enlazada(PDO $pdo, array $filt): array
{
    if (!rep_fin_columna_existe($pdo, 'solicitudes_credito', 'financiamiento_registro_id')) {
        return [[], []];
    }
    [$d1, $d2] = rep_fin_rango_fechas_efectivo($filt['desde'], $filt['hasta']);
    $pack = rep_fin_fetch_enlazada($pdo, $d1, $d2);
    $headers = [
        'ID financiamiento', 'Fecha', 'Cliente (público)', 'Sexo formulario', 'Género (agrupado)', 'Edad (calc.)', 'Rango edad',
        'Salario USD (form.)', 'Rango salario', 'Perfil estimado', 'Sector estimado',
        'ID solicitud', 'Estado Motus', 'Perfil Motus', 'Ingreso Motus', 'Género Motus', 'Edad Motus', 'Nombre Motus', 'Cédula Motus',
        '¿Perfil coincide?', '¿Género coincide?',
    ];
    $rows = [];
    foreach ($pack['rows'] as $row) {
        $e = rep_fin_enriquecer_fila_enlazada($row);
        if (!rep_fin_pasar_filtro($e, $filt, true)) {
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
            $e['ingreso_motus'] ?? '',
            $e['genero_motus'] ?? '',
            $e['edad_motus'] ?? '',
            $e['nombre_motus'] ?? '',
            $e['cedula_motus'] ?? '',
            !empty($e['perfil_motus_coincide']) ? 'Sí' : 'No',
            !empty($e['genero_motus_coincide']) ? 'Sí' : 'No',
        ];
    }

    return [$headers, $rows];
}
