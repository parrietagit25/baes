-- ============================================================
-- FASE 5: Validación funcional post-migración
-- Solo lectura. Verifica integridad referencial y consistencia.
-- ============================================================

SELECT '== TOTALES POST-RECUPERACION ==' AS seccion;
SELECT
  (SELECT COUNT(*) FROM motus_baes.solicitudes_credito)      AS total_cabeceras,
  (SELECT COUNT(*) FROM motus_baes.solicitudes_credito WHERE cedula LIKE 'RECUPERADO-%') AS reconstruidas,
  (SELECT COUNT(*) FROM motus_baes.solicitudes_credito WHERE cedula NOT LIKE 'RECUPERADO-%') AS originales;

-- 2. Huérfanos: filas en tablas relacionadas que YA NO deberían existir como huérfanos
SELECT '== HUERFANOS RESIDUALES POR TABLA ==' AS seccion;
SELECT
  (SELECT COUNT(*) FROM motus_baes.vehiculos_solicitud v
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = v.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_vehiculos,
  (SELECT COUNT(*) FROM motus_baes.usuarios_banco_solicitudes ubs
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = ubs.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_ubs,
  (SELECT COUNT(*) FROM motus_baes.evaluaciones_banco e
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = e.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_evaluaciones,
  (SELECT COUNT(*) FROM motus_baes.notas_solicitud n
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = n.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_notas,
  (SELECT COUNT(*) FROM motus_baes.adjuntos_solicitud a
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = a.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_adjuntos,
  (SELECT COUNT(*) FROM motus_baes.historial_solicitud h
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = h.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_historial,
  (SELECT COUNT(*) FROM motus_baes.citas_firma c
     LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = c.solicitud_id
     WHERE sc.id IS NULL) AS huerfanos_citas;

-- 3. Distribución por estado/respuesta
SELECT '== DISTRIBUCION POR ESTADO ==' AS seccion;
SELECT estado, COUNT(*) AS total FROM motus_baes.solicitudes_credito GROUP BY estado ORDER BY total DESC;

SELECT '== DISTRIBUCION POR RESPUESTA_BANCO ==' AS seccion;
SELECT respuesta_banco, COUNT(*) AS total FROM motus_baes.solicitudes_credito GROUP BY respuesta_banco ORDER BY total DESC;

-- 4. Sample de filas reconstruidas
SELECT '== MUESTRA DE FILAS RECONSTRUIDAS (10) ==' AS seccion;
SELECT id, gestor_id, banco_id, marca_auto, modelo_auto, `año_auto`, respuesta_banco, estado, fecha_creacion
FROM motus_baes.solicitudes_credito
WHERE cedula LIKE 'RECUPERADO-%'
ORDER BY id
LIMIT 10;

-- 5. AUTO_INCREMENT actual y próximo ID esperado
SELECT '== AUTO_INCREMENT FINAL ==' AS seccion;
SELECT TABLE_SCHEMA, TABLE_NAME, AUTO_INCREMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'motus_baes' AND TABLE_NAME = 'solicitudes_credito';

SELECT '== FASE 5 COMPLETA ==' AS seccion;
