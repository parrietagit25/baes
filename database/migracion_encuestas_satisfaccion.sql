-- Encuestas de satisfacción: formulario público (vendedores) y proceso de gestor
--
-- Ejecutar sobre la misma base que use la app (XAMPP: solicitud_credito; Docker: motus_baes).
-- Ejemplos (ajuste usuario y contraseña):
--   mysql -u root -p motus_baes < database/migracion_encuestas_satisfaccion.sql
--   mysql -u motus_user -p motus_baes < database/migracion_encuestas_satisfaccion.sql
-- Desde contenedor MySQL (Docker):
--   docker exec -i motus_db mysql -u motus_user -pmotus_pass_2024 motus_baes < database/migracion_encuestas_satisfaccion.sql

SET NAMES utf8mb4;
-- Si prefiere fijar la base aquí, descomente (y quite el nombre de la base en la línea de comando):
-- USE motus_baes;

-- Encuesta 1: evaluación del formulario público (quienes comparten el link a clientes)
CREATE TABLE IF NOT EXISTS `encuesta_formulario_publico_vendedor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Usuario de sesión que envía',
  `nombre_completo` varchar(200) NOT NULL,
  `cargo` varchar(200) NOT NULL,
  `puntuacion_1` tinyint(3) unsigned NOT NULL COMMENT 'Facilidad compartir enlace con el cliente (1-5)',
  `puntuacion_2` tinyint(3) unsigned NOT NULL COMMENT 'Claridad instrucciones al cliente (1-5)',
  `puntuacion_3` tinyint(3) unsigned NOT NULL COMMENT 'Rapidez del cliente al completar el formulario (1-5)',
  `puntuacion_4` tinyint(3) unsigned NOT NULL COMMENT 'Acompañamiento/resolución de dudas en el proceso (1-5)',
  `puntuacion_5` tinyint(3) unsigned NOT NULL COMMENT 'Satisfacción general con el formulario público (1-5)',
  `recomendaciones` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado_en`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encuesta 2: evaluación del sistema/proceso desde el rol gestor
CREATE TABLE IF NOT EXISTS `encuesta_proceso_gestor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Usuario de sesión que envía',
  `nombre_completo` varchar(200) NOT NULL,
  `cargo` varchar(200) NOT NULL,
  `puntuacion_1` tinyint(3) unsigned NOT NULL COMMENT 'Agilidad al crear o gestionar solicitudes (1-5)',
  `puntuacion_2` tinyint(3) unsigned NOT NULL COMMENT 'Claridad de la información en pantalla (1-5)',
  `puntuacion_3` tinyint(3) unsigned NOT NULL COMMENT 'Comunicación vendedor/banco/equipos (1-5)',
  `puntuacion_4` tinyint(3) unsigned NOT NULL COMMENT 'Utilidad de reportes o herramientas del rol (1-5)',
  `puntuacion_5` tinyint(3) unsigned NOT NULL COMMENT 'Satisfacción general con la gestión en el sistema (1-5)',
  `recomendaciones` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado_en`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
