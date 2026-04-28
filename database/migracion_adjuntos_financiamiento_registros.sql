-- Adjuntos persistentes del formulario publico de financiamiento
-- Permite ver adjuntos en Sol Financiamiento antes de crear la solicitud de credito.

CREATE TABLE IF NOT EXISTS `adjuntos_financiamiento_registros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `financiamiento_registro_id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tipo_archivo` varchar(100) NOT NULL,
  `tamano_archivo` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fin_reg` (`financiamiento_registro_id`),
  KEY `idx_fecha_subida` (`fecha_subida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
