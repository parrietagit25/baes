-- Tasa bancaria (%) en evaluaciones del usuario banco
-- Ejecutar una sola vez: mysql -u usuario -p nombre_base < database/migracion_evaluaciones_tasa_bancaria.sql

ALTER TABLE `evaluaciones_banco`
ADD COLUMN `tasa_bancaria` decimal(6,2) NOT NULL DEFAULT 0 COMMENT 'Tasa nominal anual (%)' AFTER `promocion`;
