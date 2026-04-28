-- ============================================================
-- FASE 3: Staging y reconstrucción de solicitudes_credito
-- Trabaja en motus_baes_recovery. NO toca producción.
-- Idempotente: puede correrse varias veces.
-- ============================================================

SET @OLD_SQL_MODE := @@SESSION.sql_mode;
SET SESSION sql_mode = '';
SET @OLD_FK := @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Asegurar base motus_baes_recovery
CREATE DATABASE IF NOT EXISTS motus_baes_recovery
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE motus_baes_recovery;

-- 2. Recrear tabla destino con la MISMA estructura de producción
DROP TABLE IF EXISTS solicitudes_credito_reconstruida;
CREATE TABLE solicitudes_credito_reconstruida LIKE motus_baes.solicitudes_credito;

-- 3. Universo de IDs faltantes (presentes en relacionadas, no en producción)
DROP TEMPORARY TABLE IF EXISTS tmp_ids_faltantes;
CREATE TEMPORARY TABLE tmp_ids_faltantes (
  solicitud_id INT NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO tmp_ids_faltantes (solicitud_id)
SELECT t.solicitud_id FROM (
  SELECT DISTINCT solicitud_id FROM motus_baes.vehiculos_solicitud
  UNION SELECT DISTINCT solicitud_id FROM motus_baes.usuarios_banco_solicitudes
  UNION SELECT DISTINCT solicitud_id FROM motus_baes.evaluaciones_banco
  UNION SELECT DISTINCT solicitud_id FROM motus_baes.notas_solicitud
  UNION SELECT DISTINCT solicitud_id FROM motus_baes.adjuntos_solicitud
  UNION SELECT DISTINCT solicitud_id FROM motus_baes.historial_solicitud
  UNION SELECT DISTINCT solicitud_id FROM motus_baes.citas_firma
) t
LEFT JOIN motus_baes.solicitudes_credito sc ON sc.id = t.solicitud_id
WHERE sc.id IS NULL;

SELECT '== IDS FALTANTES A RECONSTRUIR ==' AS seccion, COUNT(*) AS total FROM tmp_ids_faltantes;

-- 4. Vistas auxiliares por solicitud
-- 4.1 Vehículo principal (orden=1 o el primero por id)
DROP TEMPORARY TABLE IF EXISTS tmp_vehiculo_principal;
CREATE TEMPORARY TABLE tmp_vehiculo_principal AS
SELECT v.*
FROM motus_baes.vehiculos_solicitud v
INNER JOIN (
  SELECT solicitud_id, MIN(id) AS id_min
  FROM motus_baes.vehiculos_solicitud
  GROUP BY solicitud_id
) m ON m.solicitud_id = v.solicitud_id AND m.id_min = v.id;

-- 4.2 Evaluación más reciente por solicitud
DROP TEMPORARY TABLE IF EXISTS tmp_evaluacion_principal;
CREATE TEMPORARY TABLE tmp_evaluacion_principal AS
SELECT e.*
FROM motus_baes.evaluaciones_banco e
INNER JOIN (
  SELECT solicitud_id, MAX(id) AS id_max
  FROM motus_baes.evaluaciones_banco
  GROUP BY solicitud_id
) m ON m.solicitud_id = e.solicitud_id AND m.id_max = e.id;

-- 4.3 Banco más usado por solicitud (a través de usuarios_banco_solicitudes)
DROP TEMPORARY TABLE IF EXISTS tmp_banco_principal;
CREATE TEMPORARY TABLE tmp_banco_principal AS
SELECT solicitud_id, banco_id
FROM (
  SELECT
    ubs.solicitud_id,
    u.banco_id,
    COUNT(*) AS apariciones,
    ROW_NUMBER() OVER (PARTITION BY ubs.solicitud_id ORDER BY COUNT(*) DESC, u.banco_id ASC) AS rn
  FROM motus_baes.usuarios_banco_solicitudes ubs
  JOIN motus_baes.usuarios u ON u.id = ubs.usuario_banco_id
  WHERE u.banco_id IS NOT NULL
  GROUP BY ubs.solicitud_id, u.banco_id
) x
WHERE rn = 1;

-- 4.4 Gestor inferido (creador en historial)
DROP TEMPORARY TABLE IF EXISTS tmp_gestor_inferido;
CREATE TEMPORARY TABLE tmp_gestor_inferido AS
SELECT solicitud_id, MIN(usuario_id) AS gestor_id
FROM motus_baes.historial_solicitud
WHERE tipo_accion = 'creacion'
GROUP BY solicitud_id;

-- 4.5 Estado más reciente desde historial (mapeado al ENUM válido)
DROP TEMPORARY TABLE IF EXISTS tmp_estado_inferido;
CREATE TEMPORARY TABLE tmp_estado_inferido AS
SELECT h.solicitud_id,
       CASE
         WHEN h.estado_nuevo IN ('Nueva','En Revisión Banco','Aprobada','Rechazada','Completada','Desistimiento')
              THEN h.estado_nuevo
         ELSE NULL
       END AS estado_norm
FROM motus_baes.historial_solicitud h
INNER JOIN (
  SELECT solicitud_id, MAX(id) AS id_max
  FROM motus_baes.historial_solicitud
  WHERE estado_nuevo IS NOT NULL
  GROUP BY solicitud_id
) m ON m.solicitud_id = h.solicitud_id AND m.id_max = h.id;

-- 4.6 Fechas mínimas/máximas por solicitud
DROP TEMPORARY TABLE IF EXISTS tmp_fechas_inferidas;
CREATE TEMPORARY TABLE tmp_fechas_inferidas AS
SELECT
  t.solicitud_id,
  COALESCE(
    (SELECT MIN(h.fecha_creacion) FROM motus_baes.historial_solicitud h WHERE h.solicitud_id = t.solicitud_id),
    (SELECT MIN(ubs.fecha_asignacion) FROM motus_baes.usuarios_banco_solicitudes ubs WHERE ubs.solicitud_id = t.solicitud_id),
    (SELECT MIN(e.fecha_evaluacion) FROM motus_baes.evaluaciones_banco e WHERE e.solicitud_id = t.solicitud_id),
    (SELECT MIN(n.fecha_creacion) FROM motus_baes.notas_solicitud n WHERE n.solicitud_id = t.solicitud_id),
    (SELECT MIN(a.fecha_subida) FROM motus_baes.adjuntos_solicitud a WHERE a.solicitud_id = t.solicitud_id),
    NOW()
  ) AS fecha_creacion,
  COALESCE(
    (SELECT MAX(h.fecha_creacion) FROM motus_baes.historial_solicitud h WHERE h.solicitud_id = t.solicitud_id),
    NOW()
  ) AS fecha_actualizacion
FROM tmp_ids_faltantes t;

-- 5. Construir solicitudes_credito_reconstruida (cabeceras sintéticas)
INSERT INTO solicitudes_credito_reconstruida (
  id,
  gestor_id,
  banco_id,
  evaluacion_seleccionada,
  evaluacion_en_reevaluacion,
  fecha_aprobacion_propuesta,
  comentario_seleccion_propuesta,
  vendedor_id,
  tipo_persona,
  nombre_cliente,
  cedula,
  edad,
  genero,
  telefono,
  telefono_principal,
  email,
  email_pipedrive,
  id_cliente_pipedrive,
  id_deal_pipedrive,
  forma_pago_pipedrive,
  direccion,
  provincia,
  distrito,
  corregimiento,
  barriada,
  casa_edif,
  numero_casa_apto,
  casado,
  hijos,
  perfil_financiero,
  ingreso,
  tiempo_laborar,
  profesion,
  ocupacion,
  nombre_empresa_negocio,
  estabilidad_laboral,
  fecha_constitucion,
  continuidad_laboral,
  marca_auto,
  modelo_auto,
  `año_auto`,
  kilometraje,
  precio_especial,
  abono_porcentaje,
  abono_monto,
  comentarios_gestor,
  ejecutivo_banco,
  respuesta_banco,
  letra,
  plazo,
  abono_banco,
  promocion,
  comentarios_ejecutivo_banco,
  respuesta_cliente,
  motivo_respuesta,
  fecha_envio_proforma,
  fecha_firma_cliente,
  fecha_poliza,
  fecha_carta_promesa,
  comentarios_fi,
  estado,
  fecha_creacion,
  fecha_actualizacion
)
SELECT
  t.solicitud_id                                                      AS id,
  COALESCE(g.gestor_id, 1)                                            AS gestor_id,
  bp.banco_id                                                         AS banco_id,
  NULL                                                                AS evaluacion_seleccionada,
  NULL                                                                AS evaluacion_en_reevaluacion,
  NULL                                                                AS fecha_aprobacion_propuesta,
  NULL                                                                AS comentario_seleccion_propuesta,
  NULL                                                                AS vendedor_id,
  'Natural'                                                           AS tipo_persona,
  CONCAT('Solicitud #', t.solicitud_id, ' (recuperada)')              AS nombre_cliente,
  CONCAT('RECUPERADO-', t.solicitud_id)                               AS cedula,
  NULL                                                                AS edad,
  NULL                                                                AS genero,
  NULL                                                                AS telefono,
  NULL                                                                AS telefono_principal,
  NULL                                                                AS email,
  NULL                                                                AS email_pipedrive,
  NULL                                                                AS id_cliente_pipedrive,
  NULL                                                                AS id_deal_pipedrive,
  NULL                                                                AS forma_pago_pipedrive,
  NULL                                                                AS direccion,
  NULL                                                                AS provincia,
  NULL                                                                AS distrito,
  NULL                                                                AS corregimiento,
  NULL                                                                AS barriada,
  NULL                                                                AS casa_edif,
  NULL                                                                AS numero_casa_apto,
  0                                                                   AS casado,
  0                                                                   AS hijos,
  'Asalariado'                                                        AS perfil_financiero,
  NULL                                                                AS ingreso,
  NULL                                                                AS tiempo_laborar,
  NULL                                                                AS profesion,
  NULL                                                                AS ocupacion,
  NULL                                                                AS nombre_empresa_negocio,
  NULL                                                                AS estabilidad_laboral,
  NULL                                                                AS fecha_constitucion,
  NULL                                                                AS continuidad_laboral,
  vp.marca                                                            AS marca_auto,
  vp.modelo                                                           AS modelo_auto,
  vp.anio                                                             AS `año_auto`,
  vp.kilometraje                                                      AS kilometraje,
  vp.precio                                                           AS precio_especial,
  vp.abono_porcentaje                                                 AS abono_porcentaje,
  vp.abono_monto                                                      AS abono_monto,
  NULL                                                                AS comentarios_gestor,
  NULL                                                                AS ejecutivo_banco,
  CASE
    WHEN ep.decision = 'aprobado'             THEN 'Aprobado'
    WHEN ep.decision = 'preaprobado'          THEN 'Pre Aprobado'
    WHEN ep.decision = 'aprobado_condicional' THEN 'Aprobado Condicional'
    WHEN ep.decision = 'rechazado'            THEN 'Rechazado'
    ELSE 'Pendiente'
  END                                                                 AS respuesta_banco,
  ep.letra                                                            AS letra,
  ep.plazo                                                            AS plazo,
  ep.abono                                                            AS abono_banco,
  ep.promocion                                                        AS promocion,
  ep.comentarios                                                      AS comentarios_ejecutivo_banco,
  'Pendiente'                                                         AS respuesta_cliente,
  NULL                                                                AS motivo_respuesta,
  NULL                                                                AS fecha_envio_proforma,
  NULL                                                                AS fecha_firma_cliente,
  NULL                                                                AS fecha_poliza,
  NULL                                                                AS fecha_carta_promesa,
  NULL                                                                AS comentarios_fi,
  COALESCE(es.estado_norm, 'Nueva')                                   AS estado,
  COALESCE(fi.fecha_creacion, NOW())                                  AS fecha_creacion,
  COALESCE(fi.fecha_actualizacion, NOW())                             AS fecha_actualizacion
FROM tmp_ids_faltantes t
LEFT JOIN tmp_vehiculo_principal   vp ON vp.solicitud_id = t.solicitud_id
LEFT JOIN tmp_evaluacion_principal ep ON ep.solicitud_id = t.solicitud_id
LEFT JOIN tmp_banco_principal      bp ON bp.solicitud_id = t.solicitud_id
LEFT JOIN tmp_gestor_inferido      g  ON g.solicitud_id  = t.solicitud_id
LEFT JOIN tmp_estado_inferido      es ON es.solicitud_id = t.solicitud_id
LEFT JOIN tmp_fechas_inferidas     fi ON fi.solicitud_id = t.solicitud_id;

SELECT '== RECONSTRUIDAS ==' AS seccion, COUNT(*) AS total FROM solicitudes_credito_reconstruida;

-- 6. Validaciones obligatorias
SELECT '== VALIDACION: IDS DUPLICADOS ==' AS seccion;
SELECT id, COUNT(*) c
FROM solicitudes_credito_reconstruida
GROUP BY id HAVING c > 1;

SELECT '== VALIDACION: COLISION CON PRODUCCION ==' AS seccion;
SELECT r.id
FROM solicitudes_credito_reconstruida r
INNER JOIN motus_baes.solicitudes_credito p ON p.id = r.id;

SELECT '== VALIDACION: NOT NULL CRITICOS ==' AS seccion;
SELECT
  SUM(gestor_id        IS NULL) AS null_gestor,
  SUM(tipo_persona     IS NULL OR tipo_persona = '') AS null_tipo_persona,
  SUM(nombre_cliente   IS NULL OR nombre_cliente = '') AS null_nombre,
  SUM(cedula           IS NULL OR cedula = '') AS null_cedula,
  SUM(perfil_financiero IS NULL OR perfil_financiero = '') AS null_perfil
FROM solicitudes_credito_reconstruida;

SELECT '== VALIDACION: ESTADOS FUERA DE ENUM ==' AS seccion;
SELECT id, estado
FROM solicitudes_credito_reconstruida
WHERE estado NOT IN ('Nueva','En Revisión Banco','Aprobada','Rechazada','Completada','Desistimiento');

SELECT '== VALIDACION: RESPUESTA_BANCO FUERA DE ENUM ==' AS seccion;
SELECT id, respuesta_banco
FROM solicitudes_credito_reconstruida
WHERE respuesta_banco NOT IN ('Pendiente','Aprobado','Pre Aprobado','Aprobado Condicional','Rechazado');

-- 7. Restaurar sesión
SET SESSION sql_mode    = @OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS  = @OLD_FK;

SELECT '== FASE 3 COMPLETA ==' AS seccion;
