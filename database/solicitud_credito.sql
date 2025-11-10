-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-11-2025 a las 00:50:06
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `solicitud_credito`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adjuntos_solicitud`
--

CREATE TABLE `adjuntos_solicitud` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tipo_archivo` varchar(100) NOT NULL,
  `tamaño_archivo` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bancos`
--

CREATE TABLE `bancos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `contacto_principal` varchar(255) DEFAULT NULL,
  `telefono_contacto` varchar(20) DEFAULT NULL,
  `email_contacto` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bancos`
--

INSERT INTO `bancos` (`id`, `nombre`, `codigo`, `descripcion`, `direccion`, `telefono`, `email`, `sitio_web`, `contacto_principal`, `telefono_contacto`, `email_contacto`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Banco General', 'BG', 'Banco General de Panamá', 'Av. Balboa, Panamá', '+507 227-5000', 'info@bgeneral.com', 'https://www.bgeneral.com', 'Juan Pérez', '+507 227-5001', 'jperez@bgeneral.com', 1, '2025-09-26 00:18:37', '2025-09-26 00:18:37'),
(2, 'Banco Nacional de Panamá', 'BNP', 'Banco Nacional de Panamá', 'Calle 50, Panamá', '+507 227-6000', 'info@banconal.com', 'https://www.banconal.com', 'María González', '+507 227-6001', 'mgonzalez@banconal.com', 1, '2025-09-26 00:18:37', '2025-09-26 00:18:37'),
(3, 'Caja de Ahorros', 'CA', 'Caja de Ahorros de Panamá', 'Av. Central, Panamá', '+507 227-7000', 'info@cajadeahorros.com', 'https://www.cajadeahorros.com', 'Carlos Rodríguez', '+507 227-7001', 'crodriguez@cajadeahorros.com', 1, '2025-09-26 00:18:37', '2025-09-26 00:18:37'),
(4, 'Banistmo', 'BAN', 'Banistmo Panamá', 'Torre Banistmo, Panamá', '+507 227-8000', 'info@banistmo.com', 'https://www.banistmo.com', 'Ana Martínez', '+507 227-8001', 'amartinez@banistmo.com', 1, '2025-09-26 00:18:37', '2025-09-26 00:18:37'),
(5, 'Global Bank', 'GB', 'Global Bank Corporation', 'Calle 50, Panamá', '+507 227-9000', 'info@globalbank.com', 'https://www.globalbank.com', 'Luis Fernández', '+507 227-9001', 'lfernandez@globalbank.com', 1, '2025-09-26 00:18:37', '2025-09-26 00:18:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas_firma`
--

CREATE TABLE `citas_firma` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` time NOT NULL,
  `comentarios` text DEFAULT NULL,
  `asistio` enum('pendiente','asistio','no_asistio') DEFAULT 'pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_solicitud`
--

CREATE TABLE `documentos_solicitud` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tipo_documento` varchar(100) DEFAULT NULL,
  `tamaño_archivo` int(11) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estadisticas_importacion`
--

CREATE TABLE `estadisticas_importacion` (
  `id` int(11) NOT NULL,
  `fecha_importacion` date NOT NULL,
  `total_importados` int(11) DEFAULT 0,
  `total_errores` int(11) DEFAULT 0,
  `archivo_original` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones_banco`
--

CREATE TABLE `evaluaciones_banco` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `usuario_banco_id` int(11) NOT NULL,
  `decision` enum('preaprobado','aprobado','aprobado_condicional','rechazado') NOT NULL,
  `valor_financiar` decimal(15,2) DEFAULT NULL,
  `abono` decimal(15,2) DEFAULT NULL,
  `plazo` int(11) DEFAULT NULL,
  `letra` decimal(15,2) DEFAULT NULL,
  `promocion` varchar(255) DEFAULT NULL,
  `comentarios` text DEFAULT NULL,
  `fecha_evaluacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_solicitud`
--

CREATE TABLE `mensajes_solicitud` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('general','banco','gestor') DEFAULT 'general',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas_solicitud`
--

CREATE TABLE `notas_solicitud` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `usuario_banco_id` int(11) DEFAULT NULL,
  `tipo_nota` enum('Comentario','Actualización','Documento','Respuesta Banco','Respuesta Cliente') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(1, 'ROLE_ADMIN', 'Administrador del sistema con acceso completo', 1, '2025-08-13 22:10:58'),
(2, 'ROLE_SUPERVISOR', 'Supervisor con acceso a reportes y gestión', 1, '2025-08-13 22:10:58'),
(3, 'ROLE_USER', 'Usuario estándar con acceso básico', 1, '2025-08-13 22:10:58'),
(4, 'ROLE_AM', 'Asistente de Marketing', 1, '2025-08-13 22:10:58'),
(5, 'ROLE_VENDEDOR', 'Vendedor del sistema', 1, '2025-08-13 22:10:58'),
(6, 'ROLE_COBRADOR', 'Cobrador del sistema', 1, '2025-08-13 22:10:58'),
(7, 'ROLE_GESTOR', 'Gestor de crédito - puede crear y gestionar solicitudes', 1, '2025-09-12 15:38:08'),
(8, 'ROLE_BANCO', 'Analista bancario - puede aprobar/rechazar solicitudes', 1, '2025-09-12 15:38:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_credito`
--

CREATE TABLE `solicitudes_credito` (
  `id` int(11) NOT NULL,
  `gestor_id` int(11) NOT NULL,
  `banco_id` int(11) DEFAULT NULL,
  `evaluacion_seleccionada` int(11) DEFAULT NULL,
  `evaluacion_en_reevaluacion` int(11) DEFAULT NULL,
  `fecha_aprobacion_propuesta` timestamp NULL DEFAULT NULL,
  `comentario_seleccion_propuesta` text DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `tipo_persona` enum('Natural','Juridica') NOT NULL,
  `nombre_cliente` varchar(255) NOT NULL,
  `cedula` varchar(50) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `genero` enum('Masculino','Femenino','Otro') DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `telefono_principal` varchar(20) DEFAULT NULL COMMENT 'Teléfono principal de Pipedrive (prioritario)',
  `email` varchar(255) DEFAULT NULL,
  `email_pipedrive` varchar(255) DEFAULT NULL COMMENT 'Email para comunicación con Pipedrive',
  `id_cliente_pipedrive` varchar(50) DEFAULT NULL COMMENT 'ID del cliente en Pipedrive',
  `id_deal_pipedrive` varchar(50) DEFAULT NULL COMMENT 'ID del deal en Pipedrive',
  `forma_pago_pipedrive` varchar(50) DEFAULT NULL COMMENT 'Forma de pago desde Pipedrive',
  `direccion` text DEFAULT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `distrito` varchar(100) DEFAULT NULL,
  `corregimiento` varchar(100) DEFAULT NULL,
  `barriada` varchar(100) DEFAULT NULL,
  `casa_edif` varchar(100) DEFAULT NULL,
  `numero_casa_apto` varchar(50) DEFAULT NULL,
  `casado` tinyint(1) DEFAULT 0,
  `hijos` int(11) DEFAULT 0,
  `perfil_financiero` enum('Asalariado','Jubilado','Independiente') NOT NULL,
  `ingreso` decimal(15,2) DEFAULT NULL,
  `tiempo_laborar` varchar(100) DEFAULT NULL,
  `profesion` varchar(255) DEFAULT NULL,
  `ocupacion` varchar(255) DEFAULT NULL,
  `nombre_empresa_negocio` varchar(255) DEFAULT NULL,
  `estabilidad_laboral` date DEFAULT NULL,
  `fecha_constitucion` date DEFAULT NULL,
  `continuidad_laboral` varchar(255) DEFAULT NULL,
  `marca_auto` varchar(100) DEFAULT NULL,
  `modelo_auto` varchar(100) DEFAULT NULL,
  `año_auto` int(11) DEFAULT NULL,
  `kilometraje` int(11) DEFAULT NULL,
  `precio_especial` decimal(15,2) DEFAULT NULL,
  `abono_porcentaje` decimal(5,2) DEFAULT NULL,
  `abono_monto` decimal(15,2) DEFAULT NULL,
  `comentarios_gestor` text DEFAULT NULL,
  `ejecutivo_banco` varchar(255) DEFAULT NULL,
  `respuesta_banco` enum('Pendiente','Aprobado','Pre Aprobado','Aprobado Condicional','Rechazado') DEFAULT 'Pendiente',
  `letra` decimal(15,2) DEFAULT NULL,
  `plazo` int(11) DEFAULT NULL,
  `abono_banco` decimal(15,2) DEFAULT NULL,
  `promocion` varchar(255) DEFAULT NULL,
  `comentarios_ejecutivo_banco` text DEFAULT NULL,
  `respuesta_cliente` enum('Pendiente','Acepta','Rechaza') DEFAULT 'Pendiente',
  `motivo_respuesta` text DEFAULT NULL,
  `fecha_envio_proforma` date DEFAULT NULL,
  `fecha_firma_cliente` date DEFAULT NULL,
  `fecha_poliza` date DEFAULT NULL,
  `fecha_carta_promesa` date DEFAULT NULL,
  `comentarios_fi` text DEFAULT NULL,
  `estado` enum('Nueva','En Revisión Banco','Aprobada','Rechazada','Completada','Desistimiento') DEFAULT 'Nueva',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `banco_id` int(11) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `id_cobrador` varchar(50) DEFAULT NULL,
  `id_vendedor` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `primer_acceso` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `email`, `password`, `pais`, `cargo`, `banco_id`, `telefono`, `id_cobrador`, `id_vendedor`, `activo`, `primer_acceso`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Administrador', 'Sistema', 'admin@sistema.com', '$2y$10$NtEbfuJZktlHbn7qZukSIuJnxK5AZOdZghon8eGwWYcjwXwEdA8Pe', 'México', 'Administrador del Sistema', NULL, NULL, NULL, NULL, 1, 0, '2025-08-13 22:10:58', '2025-08-13 22:16:06'),
(2, 'Ana', 'Banco', 'banco@sistema.com', '$2y$10$oPPA5VrlvsDkgUTdsuBoV.ZncCe8nw624c3qc48gVONYgAchGXZoy', 'M├®xico', 'Analista Bancario', 1, '555-0001', '', '', 1, 0, '2025-09-29 19:35:04', '2025-10-01 03:53:31'),
(3, 'Carlos', 'Gestor', 'gestor@sistema.com', '$2y$10$KPA906Oaj5Dh4wDBnSqNi.nuQ9ZxRm4kQvoU0KBe1YO1hGHhFiili', 'México', 'vendedor', NULL, '555-0002', NULL, NULL, 1, 0, '2025-09-29 19:35:04', '2025-10-01 05:50:34'),
(4, 'bg2', 'bg2', 'bg2@bg2.com', '$2y$10$EQWT5x2/1ob6I3EWBre1CeuDAxLppdbKu953b4boDeRC0L0k2G0Ym', '', '', 1, '', NULL, NULL, 1, 1, '2025-10-27 16:02:42', '2025-10-27 16:02:42'),
(5, 'bg3', 'bg3', 'bg3@bg3.com', '$2y$10$53nS0279A/UsDAnwMZW2Aep6MasAZzs4wOnCiBtSR0uJNEb1kIShy', '', '', 1, '', NULL, NULL, 1, 1, '2025-10-27 16:03:34', '2025-10-27 16:03:34'),
(6, 'bg4', 'bg4', 'bg4@bg4.com', '$2y$10$SrP2cfb7kb2fGPpBGrlka.VphY7XgRTiclKeDKIrV2BgDKXlVI/cO', '', '', 1, '', NULL, NULL, 1, 1, '2025-10-27 16:04:08', '2025-10-27 16:04:08'),
(7, 'bg5', 'bg5', 'bg5@bg5.com', '$2y$10$8K6uVsytkSFZtL6qPr71IObt3kvbQFatLqw9lWAfkewrQG7GQRLg.', '', '', 1, '', NULL, NULL, 1, 1, '2025-10-27 16:06:09', '2025-10-27 16:06:09'),
(8, 'bcn1', 'bcn1', 'bcn1@bcn1.com', '$2y$10$iZqxwUqLInJ0LLQnq7KCVunsTXAzDw.kjDy3GQHlWIqkrC5C0ab2q', '', '', 2, '', NULL, NULL, 1, 1, '2025-10-27 16:07:01', '2025-10-27 16:07:01'),
(9, 'bcn2', 'bcn2', 'bcn2@bcn2.com', '$2y$10$1cYIkPHK0Ka0EseUS.7MVe7ohoXPkMZ2dSwlMZc0DOfqeW15Tjv5S', '', '', 2, '', NULL, NULL, 1, 1, '2025-10-27 16:07:43', '2025-10-27 16:07:43'),
(10, 'bcn3', 'bcn3', 'bcn3@bcn3.com', '$2y$10$fYA5l44JLQeOjXcMwn/IeOdTneOEcY0YaUmJOjX7Kt9McdoJs6WTC', '', '', 2, '', NULL, NULL, 1, 1, '2025-10-27 16:08:16', '2025-10-27 16:08:16'),
(11, 'bcn4', 'bcn4', 'bcn4@bcn4.com', '$2y$10$G.wxv2O6zhlJZHJwvFFfa.VDc1k3VUp6FrN5u0AYz83Osb6mUNHmK', '', '', 2, '', NULL, NULL, 1, 1, '2025-10-27 16:10:38', '2025-10-27 16:10:38'),
(12, 'bcn5', 'bcn5', 'bcn5@bcn5.com', '$2y$10$cQI1V6QqLZ7MTs2E0ZOBSef1EqWISIH2yH59MnvhZBPUk9nK/Evj2', '', '', 2, '', NULL, NULL, 1, 1, '2025-10-27 16:11:10', '2025-10-27 16:11:10'),
(13, 'ban1', 'ban1', 'ban1@ban1.com', '$2y$10$MOGt8mH7C5U18ugP.ncSreiL9k28nkLeHPzEdej6J1mpX1Q1B4GtO', '', '', 4, '', NULL, NULL, 1, 1, '2025-10-27 16:12:08', '2025-10-27 16:12:08'),
(14, 'ban2', 'ban2', 'ban2@ban2.com', '$2y$10$sQJCFmI8uexqLZrn6a8eze3DEeIhb5484dMSBtYi9cC2D5ehDDFT2', '', '', 4, '', NULL, NULL, 1, 1, '2025-10-27 16:12:49', '2025-10-27 16:12:49'),
(15, 'ban3', 'ban3', 'ban3@ban3.com', '$2y$10$Y8dsJFBZResFo2mtPIhhC.merNhTLwEnXPDUwP7aM08dRYzPdsYim', '', '', 4, '', NULL, NULL, 1, 1, '2025-10-27 16:13:55', '2025-10-27 16:13:55'),
(16, 'ban4', 'ban4', 'ban4@ban4.com', '$2y$10$fxmqHF0Okt1ndxZuwYE6a.2.ePKjs8VEcSJeTTbYn73oMajiDY.FO', '', '', 4, '', NULL, NULL, 1, 1, '2025-10-27 16:22:10', '2025-10-27 16:22:10'),
(17, 'ban5', 'ban5', 'ban5@ban5.com', '$2y$10$Q1VRSU5bpo2O1t11Qxveee/KmdJ/XIiwBA86IXeEyPPe0HBnOk4hm', '', '', 4, '', NULL, NULL, 1, 1, '2025-10-27 16:22:56', '2025-10-27 16:22:56'),
(18, 'gestor2', 'gestor2', 'gestor2@gestor2.com', '$2y$10$h9rqDtYV9FFYvdk1ETO9YeOAJpMKXLQW4yC9SaC5p9QpMTmt96R6C', '', '', NULL, '', NULL, NULL, 1, 1, '2025-10-27 16:23:37', '2025-10-27 16:23:37'),
(19, 'gestor3', 'gestor3', 'gestor3@gestor3.com', '$2y$10$ztF6FWE3pHQsWfafk7Q6vuLXk2Wln0qgVEKefQWyE4ARlxljg0rru', '', '', NULL, '', NULL, NULL, 1, 1, '2025-10-27 16:24:26', '2025-10-27 16:24:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_banco_solicitudes`
--

CREATE TABLE `usuarios_banco_solicitudes` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `usuario_banco_id` int(11) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_desactivacion` timestamp NULL DEFAULT NULL,
  `creado_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_roles`
--

CREATE TABLE `usuario_roles` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario_roles`
--

INSERT INTO `usuario_roles` (`id`, `usuario_id`, `rol_id`, `fecha_asignacion`) VALUES
(1, 1, 1, '2025-08-13 22:10:58'),
(10, 2, 8, '2025-10-01 03:53:31'),
(20, 3, 7, '2025-10-03 04:25:43'),
(21, 4, 8, '2025-10-27 16:02:42'),
(22, 5, 8, '2025-10-27 16:03:34'),
(23, 6, 8, '2025-10-27 16:04:08'),
(24, 7, 8, '2025-10-27 16:06:09'),
(25, 8, 8, '2025-10-27 16:07:01'),
(26, 9, 8, '2025-10-27 16:07:43'),
(27, 10, 8, '2025-10-27 16:08:16'),
(28, 11, 8, '2025-10-27 16:10:38'),
(29, 12, 8, '2025-10-27 16:11:10'),
(30, 13, 8, '2025-10-27 16:12:08'),
(31, 14, 8, '2025-10-27 16:12:49'),
(32, 15, 8, '2025-10-27 16:13:55'),
(33, 16, 8, '2025-10-27 16:22:10'),
(34, 17, 8, '2025-10-27 16:22:56'),
(35, 18, 7, '2025-10-27 16:23:37'),
(36, 19, 7, '2025-10-27 16:24:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos_solicitud`
--

CREATE TABLE `vehiculos_solicitud` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `kilometraje` int(11) DEFAULT NULL,
  `precio` decimal(15,2) DEFAULT NULL,
  `abono_porcentaje` decimal(5,2) DEFAULT NULL,
  `abono_monto` decimal(15,2) DEFAULT NULL,
  `orden` int(11) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `adjuntos_solicitud`
--
ALTER TABLE `adjuntos_solicitud`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitud` (`solicitud_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha_subida`);

--
-- Indices de la tabla `bancos`
--
ALTER TABLE `bancos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion`);

--
-- Indices de la tabla `citas_firma`
--
ALTER TABLE `citas_firma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitud` (`solicitud_id`),
  ADD KEY `idx_fecha_cita` (`fecha_cita`);

--
-- Indices de la tabla `documentos_solicitud`
--
ALTER TABLE `documentos_solicitud`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitud` (`solicitud_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha_subida`);

--
-- Indices de la tabla `estadisticas_importacion`
--
ALTER TABLE `estadisticas_importacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha` (`fecha_importacion`),
  ADD KEY `idx_usuario` (`usuario_id`);

--
-- Indices de la tabla `evaluaciones_banco`
--
ALTER TABLE `evaluaciones_banco`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitud` (`solicitud_id`),
  ADD KEY `idx_usuario_banco` (`usuario_banco_id`),
  ADD KEY `idx_decision` (`decision`),
  ADD KEY `idx_fecha` (`fecha_evaluacion`),
  ADD KEY `idx_vehiculo` (`vehiculo_id`);

--
-- Indices de la tabla `mensajes_solicitud`
--
ALTER TABLE `mensajes_solicitud`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_mensajes_solicitud` (`solicitud_id`),
  ADD KEY `idx_mensajes_fecha` (`fecha_creacion`);

--
-- Indices de la tabla `notas_solicitud`
--
ALTER TABLE `notas_solicitud`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitud` (`solicitud_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha_creacion`),
  ADD KEY `idx_usuario_banco` (`usuario_banco_id`),
  ADD KEY `idx_vehiculo` (`vehiculo_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `solicitudes_credito`
--
ALTER TABLE `solicitudes_credito`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gestor` (`gestor_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_respuesta_banco` (`respuesta_banco`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_cedula` (`cedula`),
  ADD KEY `idx_banco` (`banco_id`),
  ADD KEY `idx_vendedor` (`vendedor_id`),
  ADD KEY `idx_evaluacion_seleccionada` (`evaluacion_seleccionada`),
  ADD KEY `idx_evaluacion_reevaluacion` (`evaluacion_en_reevaluacion`),
  ADD KEY `idx_fecha_aprobacion_propuesta` (`fecha_aprobacion_propuesta`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_banco` (`banco_id`);

--
-- Indices de la tabla `usuarios_banco_solicitudes`
--
ALTER TABLE `usuarios_banco_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_asignacion` (`solicitud_id`,`usuario_banco_id`),
  ADD KEY `creado_por` (`creado_por`),
  ADD KEY `idx_usuarios_banco_solicitud` (`solicitud_id`),
  ADD KEY `idx_usuarios_banco_usuario` (`usuario_banco_id`),
  ADD KEY `idx_usuarios_banco_estado` (`estado`);

--
-- Indices de la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_usuario_rol` (`usuario_id`,`rol_id`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Indices de la tabla `vehiculos_solicitud`
--
ALTER TABLE `vehiculos_solicitud`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitud` (`solicitud_id`),
  ADD KEY `idx_orden` (`orden`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adjuntos_solicitud`
--
ALTER TABLE `adjuntos_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `bancos`
--
ALTER TABLE `bancos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `citas_firma`
--
ALTER TABLE `citas_firma`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `documentos_solicitud`
--
ALTER TABLE `documentos_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estadisticas_importacion`
--
ALTER TABLE `estadisticas_importacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evaluaciones_banco`
--
ALTER TABLE `evaluaciones_banco`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `mensajes_solicitud`
--
ALTER TABLE `mensajes_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `notas_solicitud`
--
ALTER TABLE `notas_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `solicitudes_credito`
--
ALTER TABLE `solicitudes_credito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `usuarios_banco_solicitudes`
--
ALTER TABLE `usuarios_banco_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `vehiculos_solicitud`
--
ALTER TABLE `vehiculos_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `adjuntos_solicitud`
--
ALTER TABLE `adjuntos_solicitud`
  ADD CONSTRAINT `adjuntos_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adjuntos_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `citas_firma`
--
ALTER TABLE `citas_firma`
  ADD CONSTRAINT `citas_firma_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `documentos_solicitud`
--
ALTER TABLE `documentos_solicitud`
  ADD CONSTRAINT `documentos_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documentos_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estadisticas_importacion`
--
ALTER TABLE `estadisticas_importacion`
  ADD CONSTRAINT `estadisticas_importacion_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `evaluaciones_banco`
--
ALTER TABLE `evaluaciones_banco`
  ADD CONSTRAINT `evaluaciones_banco_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluaciones_banco_ibfk_2` FOREIGN KEY (`usuario_banco_id`) REFERENCES `usuarios_banco_solicitudes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluaciones_banco_ibfk_3` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos_solicitud` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mensajes_solicitud`
--
ALTER TABLE `mensajes_solicitud`
  ADD CONSTRAINT `mensajes_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensajes_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notas_solicitud`
--
ALTER TABLE `notas_solicitud`
  ADD CONSTRAINT `fk_nota_usuario_banco` FOREIGN KEY (`usuario_banco_id`) REFERENCES `usuarios_banco_solicitudes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nota_vehiculo` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos_solicitud` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_credito`
--
ALTER TABLE `solicitudes_credito`
  ADD CONSTRAINT `fk_solicitudes_banco` FOREIGN KEY (`banco_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_solicitudes_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_credito_ibfk_1` FOREIGN KEY (`gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solicitudes_credito_ibfk_2` FOREIGN KEY (`evaluacion_seleccionada`) REFERENCES `evaluaciones_banco` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_credito_ibfk_3` FOREIGN KEY (`evaluacion_en_reevaluacion`) REFERENCES `evaluaciones_banco` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`banco_id`) REFERENCES `bancos` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `usuarios_banco_solicitudes`
--
ALTER TABLE `usuarios_banco_solicitudes`
  ADD CONSTRAINT `usuarios_banco_solicitudes_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuarios_banco_solicitudes_ibfk_2` FOREIGN KEY (`usuario_banco_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuarios_banco_solicitudes_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  ADD CONSTRAINT `usuario_roles_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_roles_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vehiculos_solicitud`
--
ALTER TABLE `vehiculos_solicitud`
  ADD CONSTRAINT `vehiculos_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
