-- Aplicar solo si `ejecutivos_ventas` ya existía sin estas columnas (omitir líneas que fallen con "Duplicate column").

ALTER TABLE `ejecutivos_ventas` ADD COLUMN `activo` tinyint(1) NOT NULL DEFAULT 1 AFTER `email`;
ALTER TABLE `ejecutivos_ventas` ADD COLUMN `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `activo`;
ALTER TABLE `ejecutivos_ventas` ADD COLUMN `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `fecha_creacion`;
ALTER TABLE `ejecutivos_ventas` ADD KEY `idx_activo` (`activo`);
