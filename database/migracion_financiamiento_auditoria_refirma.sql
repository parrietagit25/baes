-- Auditoría de ediciones admin en Sol Financiamiento + tokens para refirmar (enlace 30 min).
-- Ejecutar en la misma base de datos donde existe `financiamiento_registros` (ej. motus_baes).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `financiamiento_registro_auditoria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `financiamiento_registro_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Usuario admin que aplicó el cambio',
  `fecha_modificacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cambios_json` longtext NOT NULL COMMENT 'JSON: campo -> {old, new}',
  PRIMARY KEY (`id`),
  KEY `idx_fin_registro` (`financiamiento_registro_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_fecha` (`fecha_modificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financiamiento_refirma_token` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `financiamiento_registro_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL COMMENT 'Token aleatorio (hex)',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_usuario_id` int(11) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_registro_expira` (`financiamiento_registro_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
