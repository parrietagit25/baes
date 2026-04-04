-- Catálogo Ejecutivos de Ventas (mantenimiento en ejecutivos_ventas.php, solo admin).
-- Si la tabla ya existía solo con id/nombre/sucursal/email, ejecute después:
--   database/migracion_ejecutivos_ventas_columnas_extra.sql

CREATE TABLE IF NOT EXISTS `ejecutivos_ventas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `sucursal` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
