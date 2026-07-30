-- Seguimiento extremo de actividad de usuarios internos MOTUS.
-- Solo consultable por el administrador principal (usuarios.id = 1).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `usuario_actividad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `session_key` varchar(64) DEFAULT NULL,
  `evento` varchar(40) NOT NULL COMMENT 'login, logout, page_view, click, action, heartbeat, visibility, login_failed',
  `pagina` varchar(120) DEFAULT NULL,
  `seccion` varchar(120) DEFAULT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `url_path` varchar(500) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ua_usuario_fecha` (`usuario_id`, `created_at`),
  KEY `idx_ua_evento_fecha` (`evento`, `created_at`),
  KEY `idx_ua_created` (`created_at`),
  KEY `idx_ua_session` (`session_key`),
  KEY `idx_ua_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
