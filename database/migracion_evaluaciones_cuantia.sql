-- Cuantía de la promoción en evaluaciones del banco
-- Ejecutar una sola vez. Si la columna ya existe, ignore Duplicate column.

ALTER TABLE `evaluaciones_banco`
  ADD COLUMN `cuantia` decimal(15,2) DEFAULT NULL
  COMMENT 'Cuantía de la promoción seleccionada'
  AFTER `promocion`;
