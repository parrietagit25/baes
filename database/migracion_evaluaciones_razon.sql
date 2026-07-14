-- Razón de la decisión en evaluaciones del banco
-- Ejecutar una sola vez. Si la columna ya existe, ignore Duplicate column.

ALTER TABLE `evaluaciones_banco`
  ADD COLUMN `razon` text DEFAULT NULL
  COMMENT 'Razón de la decisión del banco'
  AFTER `decision`;
