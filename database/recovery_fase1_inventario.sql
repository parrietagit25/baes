-- ============================================================
-- FASE 1: Inventario y diagnóstico de cobertura de recuperación
-- Solo lectura. Ejecutar en motus_db.
-- ============================================================

-- 1. Conteo global por tabla relacionada
SELECT '== TOTALES POR TABLA ==' AS seccion;

SELECT
  (SELECT COUNT(*) FROM motus_baes.solicitudes_credito)        AS cab_solicitudes_credito,
  (SELECT COUNT(*) FROM motus_baes.vehiculos_solicitud)        AS rel_vehiculos_solicitud,
  (SELECT COUNT(*) FROM motus_baes.usuarios_banco_solicitudes) AS rel_usuarios_banco_solicitudes,
  (SELECT COUNT(*) FROM motus_baes.evaluaciones_banco)         AS rel_evaluaciones_banco,
  (SELECT COUNT(*) FROM motus_baes.notas_solicitud)            AS rel_notas_solicitud,
  (SELECT COUNT(*) FROM motus_baes.adjuntos_solicitud)         AS rel_adjuntos_solicitud,
  (SELECT COUNT(*) FROM motus_baes.historial_solicitud)        AS rel_historial_solicitud,
  (SELECT COUNT(*) FROM motus_baes.citas_firma)                AS rel_citas_firma;

-- 2. IDs distintos de solicitud por cada tabla
SELECT '== IDS DISTINTOS POR TABLA ==' AS seccion;

SELECT
  (SELECT COUNT(DISTINCT id)            FROM motus_baes.solicitudes_credito)        AS ids_cabecera,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.vehiculos_solicitud)        AS ids_vehiculos,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.usuarios_banco_solicitudes) AS ids_ubs,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.evaluaciones_banco)         AS ids_evaluaciones,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.notas_solicitud)            AS ids_notas,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.adjuntos_solicitud)         AS ids_adjuntos,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.historial_solicitud)        AS ids_historial,
  (SELECT COUNT(DISTINCT solicitud_id)  FROM motus_baes.citas_firma)                AS ids_citas;

-- 3. Construir vista temporal con la unión de TODOS los solicitud_id en relacionadas
DROP TEMPORARY TABLE IF EXISTS tmp_ids_relacionados;
CREATE TEMPORARY TABLE tmp_ids_relacionados (
  solicitud_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO tmp_ids_relacionados (solicitud_id)
SELECT DISTINCT solicitud_id FROM motus_baes.vehiculos_solicitud
UNION
SELECT DISTINCT solicitud_id FROM motus_baes.usuarios_banco_solicitudes
UNION
SELECT DISTINCT solicitud_id FROM motus_baes.evaluaciones_banco
UNION
SELECT DISTINCT solicitud_id FROM motus_baes.notas_solicitud
UNION
SELECT DISTINCT solicitud_id FROM motus_baes.adjuntos_solicitud
UNION
SELECT DISTINCT solicitud_id FROM motus_baes.historial_solicitud
UNION
SELECT DISTINCT solicitud_id FROM motus_baes.citas_firma;

SELECT '== UNIVERSO DE IDS RECUPERABLES ==' AS seccion;

SELECT
  COUNT(*)        AS total_ids_relacionados,
  MIN(solicitud_id) AS min_id_rel,
  MAX(solicitud_id) AS max_id_rel
FROM tmp_ids_relacionados;

-- 4. Brecha: IDs en relacionadas que NO están en solicitudes_credito
SELECT '== IDS FALTANTES EN solicitudes_credito ==' AS seccion;

SELECT
  COUNT(*) AS ids_faltantes,
  MIN(t.solicitud_id) AS min_faltante,
  MAX(t.solicitud_id) AS max_faltante
FROM tmp_ids_relacionados t
LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = t.solicitud_id
WHERE sc.id IS NULL;

-- Listado completo de IDs faltantes (ordenado)
SELECT '== LISTADO DE IDS FALTANTES (HASTA 500) ==' AS seccion;
SELECT t.solicitud_id
FROM tmp_ids_relacionados t
LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = t.solicitud_id
WHERE sc.id IS NULL
ORDER BY t.solicitud_id
LIMIT 500;

-- 5. Cobertura por tabla para los IDs faltantes
SELECT '== COBERTURA POR ID FALTANTE ==' AS seccion;

SELECT
  t.solicitud_id,
  EXISTS(SELECT 1 FROM motus_baes.vehiculos_solicitud v        WHERE v.solicitud_id = t.solicitud_id)  AS tiene_vehiculo,
  EXISTS(SELECT 1 FROM motus_baes.usuarios_banco_solicitudes u WHERE u.solicitud_id = t.solicitud_id)  AS tiene_ubs,
  EXISTS(SELECT 1 FROM motus_baes.evaluaciones_banco e         WHERE e.solicitud_id = t.solicitud_id)  AS tiene_evaluacion,
  EXISTS(SELECT 1 FROM motus_baes.notas_solicitud n            WHERE n.solicitud_id = t.solicitud_id)  AS tiene_nota,
  EXISTS(SELECT 1 FROM motus_baes.adjuntos_solicitud a         WHERE a.solicitud_id = t.solicitud_id)  AS tiene_adjunto,
  EXISTS(SELECT 1 FROM motus_baes.historial_solicitud h        WHERE h.solicitud_id = t.solicitud_id)  AS tiene_historial,
  EXISTS(SELECT 1 FROM motus_baes.citas_firma c                WHERE c.solicitud_id = t.solicitud_id)  AS tiene_cita
FROM tmp_ids_relacionados t
LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = t.solicitud_id
WHERE sc.id IS NULL
ORDER BY t.solicitud_id;

-- 6. Pista de datos heredables (usuario que más interactuó por solicitud)
SELECT '== USUARIOS Y BANCOS POR ID FALTANTE (PISTAS DE GESTOR/BANCO) ==' AS seccion;

SELECT
  t.solicitud_id,
  (SELECT MIN(h.usuario_id)
     FROM motus_baes.historial_solicitud h
     WHERE h.solicitud_id = t.solicitud_id
       AND h.tipo_accion = 'creacion')                                                AS posible_gestor_creador,
  (SELECT GROUP_CONCAT(DISTINCT u.banco_id ORDER BY u.banco_id SEPARATOR ',')
     FROM motus_baes.usuarios_banco_solicitudes ubs
     JOIN motus_baes.usuarios u ON u.id = ubs.usuario_banco_id
     WHERE ubs.solicitud_id = t.solicitud_id)                                         AS bancos_relacionados,
  (SELECT MIN(h.fecha_creacion)
     FROM motus_baes.historial_solicitud h
     WHERE h.solicitud_id = t.solicitud_id)                                           AS primera_actividad,
  (SELECT MAX(h.fecha_creacion)
     FROM motus_baes.historial_solicitud h
     WHERE h.solicitud_id = t.solicitud_id)                                           AS ultima_actividad
FROM tmp_ids_relacionados t
LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = t.solicitud_id
WHERE sc.id IS NULL
ORDER BY t.solicitud_id;

-- 7. Verificar si queda algún rastro útil en motus_baes_recovery o tablas con sufijo
SELECT '== TABLAS POSIBLES CON RESPALDO EN RECOVERY ==' AS seccion;

SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA IN ('motus_baes_recovery')
  AND (TABLE_NAME LIKE '%solicitud%' OR TABLE_NAME LIKE '%vehiculo%' OR TABLE_NAME LIKE '%credito%')
ORDER BY TABLE_NAME;
