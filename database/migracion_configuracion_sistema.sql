-- Parámetros globales del sistema (ej. asistente IA). Solo admin vía configuracion.php / API.
CREATE TABLE IF NOT EXISTS `configuracion_sistema` (
  `clave` varchar(64) NOT NULL,
  `valor` text NOT NULL,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion_sistema` (`clave`, `valor`) VALUES ('chatbot_habilitado', '1')
ON DUPLICATE KEY UPDATE `clave` = `clave`;

INSERT INTO `configuracion_sistema` (`clave`, `valor`) VALUES ('mantenimiento_activo', '0')
ON DUPLICATE KEY UPDATE `clave` = `clave`;
