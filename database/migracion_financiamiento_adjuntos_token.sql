-- Tokens para que el cliente suba adjuntos a un financiamiento_registros (enlace 24 h, reutilizable hasta caducar).
-- Ejecutar en la misma base donde existe `financiamiento_registros` (ej. motus_baes).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `financiamiento_adjuntos_token` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `financiamiento_registro_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL COMMENT 'Token aleatorio (hex)',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_usuario_id` int(11) NOT NULL,
  `revoked_at` datetime DEFAULT NULL COMMENT 'Invalidado al generar un enlace nuevo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fin_adj_token` (`token`),
  KEY `idx_fin_adj_reg_expira` (`financiamiento_registro_id`, `expires_at`),
  KEY `idx_fin_adj_revoked` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
