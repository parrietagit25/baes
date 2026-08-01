<?php
/**
 * Alcance de visibilidad para supervisores de sucursal (ROLE_SP_*).
 * El alcance se deduce del rol (no hay columna en usuarios):
 *   ROLE_SP_NC  → todas las sucursales
 *   ROLE_SP_TBM → TBM
 *   ROLE_SP_VIS → VIS
 *   ROLE_SP_CV  → CV
 *   ROLE_SP_DV  → CH (David / Chiriquí)
 */

require_once __DIR__ . '/banco_scope_helper.php';

/** @var array<string, list<string>|null> null = sin restricción (nacional) */
const MOTUS_SP_ROLE_CODIGOS = [
    'ROLE_SP_NC' => null,
    'ROLE_SP_TBM' => ['TBM'],
    'ROLE_SP_VIS' => ['VIS'],
    'ROLE_SP_CV' => ['CV'],
    'ROLE_SP_DV' => ['CH'],
];

function motus_es_vista_sp(?array $roles = null): bool
{
    foreach (motus_roles_usuario($roles) as $r) {
        if (is_string($r) && str_starts_with($r, 'ROLE_SP_')) {
            return true;
        }
    }
    return false;
}

function motus_es_sp_nacional(?array $roles = null): bool
{
    return in_array('ROLE_SP_NC', motus_roles_usuario($roles), true);
}

/**
 * Códigos de sucursal permitidos (CH, CV, TBM, VIS…).
 * - null: sin restricción (no es SP, o es SP_NC)
 * - []: SP sin códigos válidos (denegar)
 * - ['TBM']: solo esa(s) sucursal(es)
 *
 * @return list<string>|null
 */
function motus_sp_codigos_permitidos(?array $roles = null): ?array
{
    $roles = motus_roles_usuario($roles);
    if (!motus_es_vista_sp($roles)) {
        return null;
    }
    if (motus_es_sp_nacional($roles)) {
        return null;
    }
    $out = [];
    foreach (MOTUS_SP_ROLE_CODIGOS as $rol => $codigos) {
        if ($codigos === null) {
            continue;
        }
        if (in_array($rol, $roles, true)) {
            foreach ($codigos as $c) {
                $c = strtoupper(trim((string) $c));
                if ($c !== '') {
                    $out[$c] = true;
                }
            }
        }
    }
    return array_keys($out);
}

/**
 * Valores de ejecutivos_ventas.sucursal que cuentan para esos códigos
 * (agente "TBM" y supervisor "SP-TBM").
 *
 * @param list<string> $codigos
 * @return list<string>
 */
function motus_sp_valores_sucursal_sql(array $codigos): array
{
    $vals = [];
    foreach ($codigos as $c) {
        $c = strtoupper(trim((string) $c));
        if ($c === '') {
            continue;
        }
        $vals[$c] = true;
        $vals['SP-' . $c] = true;
    }
    return array_keys($vals);
}

/**
 * Condición SQL sobre una expresión de sucursal (ej. ev.sucursal).
 *
 * @param list<string>|null $codigos null = sin filtro
 * @return array{0:string,1:list<string>}
 */
function motus_sql_condicion_sucursal_codigos(string $exprSucursal, ?array $codigos): array
{
    if ($codigos === null) {
        return ['', []];
    }
    if ($codigos === []) {
        return [' AND 1=0 ', []];
    }
    $vals = motus_sp_valores_sucursal_sql($codigos);
    if ($vals === []) {
        return [' AND 1=0 ', []];
    }
    $ph = implode(',', array_fill(0, count($vals), '?'));
    return [" AND UPPER(TRIM({$exprSucursal})) IN ({$ph}) ", $vals];
}

/**
 * Filtro EXISTS para solicitudes_credito por ejecutivo_ventas.sucursal.
 *
 * @return array{0:string,1:list<string|int>}
 */
function motus_sql_filtro_alcance_sp(PDO $pdo, ?array $roles = null, string $colSolicitudId = 's.id'): array
{
    if (!motus_es_vista_sp($roles)) {
        return ['', []];
    }
    $codigos = motus_sp_codigos_permitidos($roles);
    if ($codigos === null) {
        return ['', []]; // SP_NC: todo
    }
    if ($codigos === []) {
        return [' AND 1=0 ', []];
    }
    $vals = motus_sp_valores_sucursal_sql($codigos);
    $ph = implode(',', array_fill(0, count($vals), '?'));
    $sql = " AND EXISTS (
        SELECT 1
        FROM ejecutivos_ventas ev_sp
        WHERE ev_sp.id = (
            SELECT sc_sp.ejecutivo_ventas_id
            FROM solicitudes_credito sc_sp
            WHERE sc_sp.id = {$colSolicitudId}
            LIMIT 1
        )
        AND UPPER(TRIM(ev_sp.sucursal)) IN ({$ph})
    ) ";
    return [$sql, $vals];
}

/**
 * Variante cuando ya hay alias de solicitudes (s) con join a ejecutivos opcional.
 * Prefiere filtrar por s.ejecutivo_ventas_id vía EXISTS corto.
 *
 * @return array{0:string,1:list<string>}
 */
function motus_sql_filtro_alcance_sp_sobre_solicitud(string $aliasSolicitud = 's', ?array $roles = null): array
{
    if (!motus_es_vista_sp($roles)) {
        return ['', []];
    }
    $codigos = motus_sp_codigos_permitidos($roles);
    if ($codigos === null) {
        return ['', []];
    }
    if ($codigos === []) {
        return [' AND 1=0 ', []];
    }
    $vals = motus_sp_valores_sucursal_sql($codigos);
    $ph = implode(',', array_fill(0, count($vals), '?'));
    $sql = " AND EXISTS (
        SELECT 1 FROM ejecutivos_ventas ev_sp
        WHERE ev_sp.id = {$aliasSolicitud}.ejecutivo_ventas_id
          AND UPPER(TRIM(ev_sp.sucursal)) IN ({$ph})
    ) ";
    return [$sql, $vals];
}

function motus_solicitud_en_alcance_sp(PDO $pdo, int $solicitudId, ?array $roles = null): bool
{
    if (!motus_es_vista_sp($roles)) {
        return true;
    }
    $codigos = motus_sp_codigos_permitidos($roles);
    if ($codigos === null) {
        return true;
    }
    if ($codigos === [] || $solicitudId <= 0) {
        return false;
    }
    $vals = motus_sp_valores_sucursal_sql($codigos);
    $ph = implode(',', array_fill(0, count($vals), '?'));
    $st = $pdo->prepare("
        SELECT 1
        FROM solicitudes_credito s
        INNER JOIN ejecutivos_ventas ev ON ev.id = s.ejecutivo_ventas_id
        WHERE s.id = ?
          AND UPPER(TRIM(ev.sucursal)) IN ({$ph})
        LIMIT 1
    ");
    $st->execute(array_merge([$solicitudId], $vals));
    return (bool) $st->fetchColumn();
}

/** Etiqueta legible del alcance SP para UI. */
function motus_sp_etiqueta_alcance(?array $roles = null): string
{
    if (!motus_es_vista_sp($roles)) {
        return '';
    }
    if (motus_es_sp_nacional($roles)) {
        return 'Todas las sucursales (SP Nacional)';
    }
    $codigos = motus_sp_codigos_permitidos($roles) ?? [];
    $nombres = [
        'CH' => 'Chiriquí / David (CH)',
        'CV' => 'Costa Verde (CV)',
        'TBM' => 'Tumbamuerto (TBM)',
        'VIS' => 'Vía Israel (VIS)',
        'BDC' => 'BDC',
    ];
    $parts = [];
    foreach ($codigos as $c) {
        $parts[] = $nombres[$c] ?? $c;
    }
    return $parts ? implode(', ', $parts) : 'Sin sucursal asignada';
}
