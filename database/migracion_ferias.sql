-- Módulo Ferias (MOTUS)
-- Tablas nuevas; no modifica tablas existentes.

CREATE TABLE IF NOT EXISTS `ferias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `lugar` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ferias_activo` (`activo`),
  KEY `idx_ferias_fechas` (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feria_vendedores` (
  `feria_id` int NOT NULL,
  `ejecutivo_ventas_id` int NOT NULL,
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feria_id`, `ejecutivo_ventas_id`),
  KEY `idx_fv_ejecutivo` (`ejecutivo_ventas_id`),
  CONSTRAINT `fk_fv_feria` FOREIGN KEY (`feria_id`) REFERENCES `ferias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fv_ejecutivo` FOREIGN KEY (`ejecutivo_ventas_id`) REFERENCES `ejecutivos_ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
