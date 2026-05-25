-- Rellena vehiculos_solicitud.unidad desde reservas ya procesadas (estado aplicado).
-- Ejecutar DESPUÉS de migracion_vehiculos_solicitud_unidad.sql
-- Requiere: reportes_reservas_lineas con columnas unidad, vehiculo_id, solicitud_id, estado

-- 1) Por vínculo directo vehiculo_id (caso ideal tras Procesar)
UPDATE `vehiculos_solicitud` AS v
INNER JOIN `reportes_reservas_lineas` AS r
  ON r.`vehiculo_id` = v.`id`
  AND r.`estado` = 'aplicado'
  AND r.`unidad` IS NOT NULL
  AND TRIM(r.`unidad`) <> ''
SET v.`unidad` = TRIM(r.`unidad`)
WHERE v.`unidad` IS NULL OR TRIM(v.`unidad`) = '';

-- 2) Respaldo: solicitud_id + primer vehículo cuando no quedó vehiculo_id en la línea
UPDATE `vehiculos_solicitud` AS v
INNER JOIN (
  SELECT r.`solicitud_id`, MIN(v2.`id`) AS vehiculo_id, MAX(TRIM(r.`unidad`)) AS unidad
  FROM `reportes_reservas_lineas` AS r
  INNER JOIN `vehiculos_solicitud` AS v2 ON v2.`solicitud_id` = r.`solicitud_id`
  WHERE r.`estado` = 'aplicado'
    AND r.`solicitud_id` IS NOT NULL
    AND r.`unidad` IS NOT NULL
    AND TRIM(r.`unidad`) <> ''
  GROUP BY r.`solicitud_id`
) AS src ON src.`vehiculo_id` = v.`id`
SET v.`unidad` = src.`unidad`
WHERE v.`unidad` IS NULL OR TRIM(v.`unidad`) = '';

-- Verificación (opcional)
-- SELECT v.id, v.solicitud_id, v.unidad, v.marca, v.modelo, v.anio
-- FROM vehiculos_solicitud v
-- WHERE v.unidad IS NOT NULL AND TRIM(v.unidad) <> ''
-- ORDER BY v.id DESC
-- LIMIT 50;
