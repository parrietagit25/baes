-- Tokens para recuperación / restablecimiento de contraseña (usuarios MOTUS).
-- Ejecutar en la base motus_baes (o la que use la app).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA-256 del token enviado por correo',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  `ip_solicitud` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
