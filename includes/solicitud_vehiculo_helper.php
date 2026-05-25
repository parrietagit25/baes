<?php
/**
 * Texto columna Vehículo en listado de solicitudes.
 * Solo muestra datos si la solicitud coincide con una reserva procesada (estado aplicado).
 *
 * @param array<string, mixed> $s
 */
function solicitud_texto_vehiculo_lista(array $s): string
{
    if ((int) ($s['tiene_reserva_aplicada'] ?? 0) <= 0) {
        return '';
    }

    $unidad = trim((string) ($s['veh_unidad'] ?? ''));
    $marca = trim((string) ($s['veh_marca'] ?? $s['marca_auto'] ?? ''));
    $modelo = trim((string) ($s['veh_modelo'] ?? $s['modelo_auto'] ?? ''));
    $anio = trim((string) ($s['veh_anio'] ?? $s['ao_auto'] ?? $s['año_auto'] ?? ''));
    $desc = trim($marca . ' ' . $modelo . ' ' . $anio);

    if ($unidad !== '' && $desc !== '') {
        return $unidad . ' — ' . $desc;
    }
    if ($unidad !== '') {
        return $unidad;
    }

    return $desc;
}

/**
 * Subconsultas SQL para unidad/vehículo y flag de reserva aplicada.
 *
 * @param string $solicitudAlias Alias de solicitudes_credito en la consulta (p. ej. s, sc)
 */
function solicitud_sql_campos_vehiculo_reserva(string $solicitudAlias = 's'): string
{
    $alias = preg_match('/^[a-z_][a-z0-9_]*$/i', $solicitudAlias) ? $solicitudAlias : 's';

    return "
        (SELECT v.unidad FROM vehiculos_solicitud v WHERE v.solicitud_id = {$alias}.id ORDER BY v.orden ASC, v.id ASC LIMIT 1) AS veh_unidad,
        (SELECT v.marca FROM vehiculos_solicitud v WHERE v.solicitud_id = {$alias}.id ORDER BY v.orden ASC, v.id ASC LIMIT 1) AS veh_marca,
        (SELECT v.modelo FROM vehiculos_solicitud v WHERE v.solicitud_id = {$alias}.id ORDER BY v.orden ASC, v.id ASC LIMIT 1) AS veh_modelo,
        (SELECT v.anio FROM vehiculos_solicitud v WHERE v.solicitud_id = {$alias}.id ORDER BY v.orden ASC, v.id ASC LIMIT 1) AS veh_anio,
        (SELECT COUNT(*) FROM reportes_reservas_lineas r WHERE r.solicitud_id = {$alias}.id AND r.estado = 'aplicado') AS tiene_reserva_aplicada
    ";
}
