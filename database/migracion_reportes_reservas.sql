-- Reportes de reservas subidos desde MOTUS (subir_reporte_reservas.php)

CREATE TABLE IF NOT EXISTS `reportes_reservas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_archivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamano_bytes` bigint unsigned DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reportes_reservas_fecha` (`fecha_subida`),
  KEY `idx_reportes_reservas_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
