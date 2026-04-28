-- ============================================================
-- FASE 4: Migración segura a producción (SWAP)
-- Inserta en motus_baes.solicitudes_credito SOLO los IDs faltantes
-- desde motus_baes_recovery.solicitudes_credito_reconstruida.
-- ============================================================

SET @OLD_SQL_MODE := @@SESSION.sql_mode;
SET SESSION sql_mode = '';
SET @OLD_FK := @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Validar que no haya colisiones de ID antes de insertar
SELECT '== PRE-CHECK COLISIONES ==' AS seccion;
SELECT COUNT(*) AS colisiones
FROM motus_baes_recovery.solicitudes_credito_reconstruida r
INNER JOIN motus_baes.solicitudes_credito p ON p.id = r.id;

-- 2. Insertar las filas reconstruidas en producción
INSERT INTO motus_baes.solicitudes_credito
SELECT * FROM motus_baes_recovery.solicitudes_credito_reconstruida r
WHERE NOT EXISTS (
  SELECT 1 FROM motus_baes.solicitudes_credito p WHERE p.id = r.id
);

-- 3. Ajustar AUTO_INCREMENT al siguiente ID disponible
SET @next_id := (SELECT IFNULL(MAX(id),0)+1 FROM motus_baes.solicitudes_credito);
SET @sql := CONCAT('ALTER TABLE motus_baes.solicitudes_credito AUTO_INCREMENT=', @next_id);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Verificación final
SELECT '== TOTAL EN PRODUCCION ==' AS seccion, COUNT(*) AS total FROM motus_baes.solicitudes_credito;
SELECT '== RANGO IDs ==' AS seccion, MIN(id) AS min_id, MAX(id) AS max_id FROM motus_baes.solicitudes_credito;
SELECT '== AUTO_INCREMENT ==' AS seccion, @next_id AS proximo_id;

-- 5. Restaurar sesión
SET SESSION sql_mode    = @OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS  = @OLD_FK;

SELECT '== FASE 4 COMPLETA ==' AS seccion;
