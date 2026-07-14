-- Letra quincenal en evaluaciones del banco (además de letra mensual en `letra`).
-- Si la columna ya existe, ignore el error Duplicate column.

ALTER TABLE `evaluaciones_banco`
  ADD COLUMN `letra_quincenal` decimal(15,2) DEFAULT NULL
  COMMENT 'Letra / cuota quincenal'
  AFTER `letra`;
