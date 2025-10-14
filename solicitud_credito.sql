-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-10-2025 a las 21:58:57
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

--
-- Volcado de datos para la tabla `mensajes_solicitud`
--

INSERT INTO `mensajes_solicitud` (`id`, `solicitud_id`, `usuario_id`, `mensaje`, `tipo`, `fecha_creacion`) VALUES
(1, 60, 1, 'se agregara un usuario, pero hay error', 'gestor', '2025-09-26 18:28:32'),
(2, 60, 1, 'prueba', 'gestor', '2025-09-26 18:30:48'),
(3, 58, 1, 'mensaje prueba', 'gestor', '2025-09-29 19:19:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas_solicitud`
--

CREATE TABLE `notas_solicitud` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_nota` enum('Comentario','Actualización','Documento','Respuesta Banco','Respuesta Cliente') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notas_solicitud`
--

INSERT INTO `notas_solicitud` (`id`, `solicitud_id`, `usuario_id`, `tipo_nota`, `titulo`, `contenido`, `fecha_creacion`) VALUES
(1, 1, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 66077390-fea7-11eb-b07e-39db335b2ae3, Persona ID: 67315)', '2025-09-15 15:51:22'),
(2, 2, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 67a55430-286c-11ec-b06e-fd2ffb0fe7e5, Persona ID: 70856)', '2025-09-15 17:59:13'),
(3, 3, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 9dadbe10-ccbc-11ec-81da-998353b91306, Persona ID: 83368)', '2025-09-15 17:59:15'),
(4, 4, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 702ae110-953f-11ed-919e-edff59e85713, Persona ID: 97658)', '2025-09-15 17:59:16'),
(5, 5, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: fdc98c60-d342-11ed-a9da-ff2ff8f98d9c, Persona ID: 62101)', '2025-09-15 17:59:17'),
(6, 6, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 99562570-d344-11ed-a9da-ff2ff8f98d9c, Persona ID: 101979)', '2025-09-15 17:59:18'),
(7, 7, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: fd2ee820-d344-11ed-94ba-e12b5499daf3, Persona ID: 101980)', '2025-09-15 17:59:18'),
(8, 8, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 66fb9e30-d7b2-11ed-8c8e-db83f6d30aeb, Persona ID: 102228)', '2025-09-15 17:59:18'),
(9, 9, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: bf791320-e136-11ed-b425-87e40197cf1f, Persona ID: 103019)', '2025-09-15 17:59:19'),
(10, 10, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 1feafbb0-f320-11ed-a0ce-bf7b0ba715dc, Persona ID: 104033)', '2025-09-15 17:59:19'),
(11, 11, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: de72a8b0-f3e5-11ed-a0ce-bf7b0ba715dc, Persona ID: 103520)', '2025-09-15 17:59:20'),
(12, 12, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 5251f410-0a53-11ee-8dc8-875e9374713a, Persona ID: 77583)', '2025-09-15 17:59:21'),
(13, 13, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: c9c3c1f0-0afc-11ee-8262-853851f61b4a, Persona ID: 107306)', '2025-09-15 17:59:21'),
(14, 14, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 184b40e0-0afe-11ee-8dc8-875e9374713a, Persona ID: 106641)', '2025-09-15 17:59:22'),
(15, 15, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 00bca510-0c64-11ee-921d-71f4c27663b8, Persona ID: 106744)', '2025-09-15 17:59:22'),
(16, 16, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: bfdbe220-0cb0-11ee-8cad-0195906a7624, Persona ID: 106776)', '2025-09-15 17:59:23'),
(17, 17, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 39f594f0-0d4e-11ee-921d-71f4c27663b8, Persona ID: 106817)', '2025-09-15 17:59:23'),
(18, 18, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 7f2c8010-0d58-11ee-b9b8-ef01b00bd05a, Persona ID: 106830)', '2025-09-15 17:59:24'),
(19, 19, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 31dc6e70-0e01-11ee-88e7-73e117da9fb9, Persona ID: 106657)', '2025-09-15 17:59:24'),
(20, 20, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: bc22e950-0e93-11ee-ad58-d97805092083, Persona ID: 106865)', '2025-09-15 17:59:24'),
(21, 21, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 094c9d70-0ecb-11ee-857e-859b729f860e, Persona ID: 106907)', '2025-09-15 17:59:25'),
(22, 22, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: e5c993c0-0ed5-11ee-ba2a-65ff4717f7eb, Persona ID: 106915)', '2025-09-15 17:59:25'),
(23, 23, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 0672acc0-0f6b-11ee-ba2a-65ff4717f7eb, Persona ID: 106964)', '2025-09-15 17:59:25'),
(24, 24, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: b82b2b80-1002-11ee-857e-859b729f860e, Persona ID: 107037)', '2025-09-15 17:59:26'),
(25, 25, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 16656b90-112d-11ee-9849-b5552ab5bf4e, Persona ID: 107170)', '2025-09-15 17:59:26'),
(26, 26, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 0b37b8b0-1149-11ee-92f1-f7ebe6180a32, Persona ID: 107211)', '2025-09-15 17:59:26'),
(27, 27, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 9e6d5d30-1174-11ee-a185-e11b1db0a4e3, Persona ID: 106113)', '2025-09-15 17:59:27'),
(28, 28, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 921e0050-11d5-11ee-aae0-654ead6221d0, Persona ID: 98334)', '2025-09-15 17:59:27'),
(29, 29, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: c646b8e0-123e-11ee-92f1-f7ebe6180a32, Persona ID: 41493)', '2025-09-15 17:59:28'),
(30, 30, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: e095dbe0-12a7-11ee-9849-b5552ab5bf4e, Persona ID: 107328)', '2025-09-15 17:59:28'),
(31, 31, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 9be74c10-12c8-11ee-aae0-654ead6221d0, Persona ID: 107356)', '2025-09-15 17:59:28'),
(32, 32, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 157f31a0-139b-11ee-aae0-654ead6221d0, Persona ID: 107770)', '2025-09-15 17:59:29'),
(33, 33, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 7d4ca120-142a-11ee-abec-f7d6bc219212, Persona ID: 107444)', '2025-09-15 17:59:29'),
(34, 34, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 73ee6e90-147c-11ee-a493-3f552a4cf134, Persona ID: 107429)', '2025-09-15 17:59:29'),
(35, 35, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 388de610-148a-11ee-a0d4-07a38bd6e17e, Persona ID: 33010)', '2025-09-15 17:59:30'),
(36, 36, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: aa674660-149d-11ee-abec-f7d6bc219212, Persona ID: 107426)', '2025-09-15 17:59:30'),
(37, 37, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 8a1b1a90-45c5-11ee-a8eb-f1d79ab836e5, Persona ID: 111835)', '2025-09-15 17:59:32'),
(38, 38, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: abfe7ec0-639a-11ee-8767-9122f4f2df4c, Persona ID: 114629)', '2025-09-15 17:59:33'),
(39, 39, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 6e862240-66a7-11ee-a788-e1593d9560fa, Persona ID: 114918)', '2025-09-15 17:59:33'),
(40, 40, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: f1e94810-7da4-11ee-982c-fdb71ce06650, Persona ID: 116906)', '2025-09-15 17:59:34'),
(41, 41, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 60962830-894b-11ee-8d3a-5da99078d4bd, Persona ID: 117645)', '2025-09-15 17:59:34'),
(42, 42, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 8e57e250-a69b-11ee-a103-fd974c612644, Persona ID: 120006)', '2025-09-15 17:59:36'),
(43, 43, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: da8e9390-c260-11ee-97c8-4f81561fa2a6, Persona ID: 122246)', '2025-09-15 17:59:37'),
(44, 44, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: ecdd6780-ccf9-11ee-81a0-9fa0eb8615f9, Persona ID: 123152)', '2025-09-15 17:59:38'),
(45, 45, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: dedbbc90-d285-11ee-8851-b36834437b13, Persona ID: 123695)', '2025-09-15 17:59:38'),
(46, 46, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 792ccb10-d8ab-11ee-a158-67f5a0dfbafe, Persona ID: 124236)', '2025-09-15 17:59:40'),
(47, 47, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: d7c65290-d8ab-11ee-8ece-bf121313c792, Persona ID: 124238)', '2025-09-15 17:59:40'),
(48, 48, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 22a36d80-d8b5-11ee-a158-67f5a0dfbafe, Persona ID: 124252)', '2025-09-15 17:59:40'),
(49, 49, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 83cea6f0-d8b6-11ee-9c94-3d58d8953fd1, Persona ID: 124253)', '2025-09-15 17:59:41'),
(50, 50, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: c342b380-d8b6-11ee-a1c6-d520d4ee68dd, Persona ID: 124254)', '2025-09-15 17:59:41'),
(51, 51, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 3f82de20-d8b7-11ee-a158-67f5a0dfbafe, Persona ID: 96234)', '2025-09-15 17:59:41'),
(52, 52, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 803e8580-dfc0-11ee-b9f3-9963250f3474, Persona ID: 124859)', '2025-09-15 17:59:42'),
(53, 53, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 42308790-e13f-11ee-9c0f-5167a00fafa8, Persona ID: 125203)', '2025-09-15 17:59:43'),
(54, 54, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: c726c7b0-e53c-11ee-89b8-fb6f82b5a6f1, Persona ID: 125367)', '2025-09-15 17:59:44'),
(55, 55, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: f1105940-e557-11ee-843f-cb415d249d93, Persona ID: 125398)', '2025-09-15 17:59:45'),
(56, 56, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: cbb6f9c0-eac4-11ee-8856-2769ed5f7e76, Persona ID: 3186)', '2025-09-15 17:59:46'),
(57, 57, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 110b4750-ee45-11ee-8bbe-03378b814a49, Persona ID: 126248)', '2025-09-15 17:59:46'),
(58, 58, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: f44fa2d0-faad-11ee-8e1e-5384cb290fce, Persona ID: 127344)', '2025-09-15 17:59:48'),
(59, 59, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 0959c9e0-fcba-11ee-b965-95cb52700b70, Persona ID: 127536)', '2025-09-15 17:59:48'),
(60, 60, 1, 'Actualización', 'Lead Importado', 'Lead importado desde Pipedrive (Lead ID: 83cd3c10-01a2-11ef-bf74-8df91e30e078, Persona ID: 127983)', '2025-09-15 17:59:49'),
(71, 58, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-09-29 19:19:07'),
(72, 58, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-09-29 19:19:17'),
(73, 60, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 00:16:33'),
(74, 59, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 01:07:36'),
(75, 60, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 01:15:17'),
(76, 60, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 01:15:36'),
(77, 60, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 03:40:33'),
(78, 59, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 03:40:44'),
(79, 60, 1, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-01 03:49:18'),
(80, 59, 1, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-01 03:50:32'),
(81, 59, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 03:50:34'),
(82, 60, 1, 'Actualización', 'Estado cambiado por administrador', 'Estado cambiado de \'En Revisión Banco\' a \'Desistimiento\'. Motivo: Prueba de funcionalidad del sistema.', '2025-10-01 04:30:50'),
(83, 58, 1, 'Actualización', 'Estado cambiado por administrador', 'Estado cambiado de \'Nueva\' a \'Aprobada\'. Motivo: es una aprobacion directa', '2025-10-01 04:42:39'),
(84, 57, 1, 'Actualización', 'Estado cambiado por administrador', 'Estado cambiado de \'Nueva\' a \'Rechazada\'. Motivo: se revido el expediente del cliente y tiene antecedentes penales ', '2025-10-01 04:44:08'),
(85, 56, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-01 05:59:15'),
(86, 58, 1, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-03 03:33:34'),
(87, 58, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-03 03:33:41'),
(88, 58, 1, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-03 03:34:59'),
(89, 58, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-03 03:35:06'),
(90, 55, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-03 04:16:45'),
(91, 3, 3, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-03 04:27:43'),
(92, 3, 3, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-03 04:27:59'),
(93, 3, 3, 'Documento', 'documento faltante', 'hoja del banco', '2025-10-03 04:29:28'),
(94, 57, 1, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-08 16:56:03'),
(95, 57, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-08 16:56:44'),
(96, 56, 1, 'Actualización', 'Solicitud enviada a revisión bancaria', 'Solicitud asignada al usuario banco: Ana Banco (Banco General). Estado cambiado a \'En Revisión Banco\'.', '2025-10-08 16:57:25'),
(97, 56, 1, 'Actualización', 'Solicitud Actualizada', 'La solicitud ha sido actualizada', '2025-10-08 16:57:26'),
(98, 56, 1, 'Actualización', 'documento faltante', 'mira falta este documento x', '2025-10-08 17:01:14'),
(99, 56, 2, 'Documento', 'documento faltante', 'falta la copia de la cedula', '2025-10-08 17:12:47'),
(100, 55, 1, 'Actualización', 'Estado cambiado por administrador', 'Estado cambiado de \'Nueva\' a \'Aprobada\'. Motivo: sñlrbmslblmslbslñmbl', '2025-10-08 17:15:49');

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
  `vendedor_id` int(11) DEFAULT NULL,
  `tipo_persona` enum('Natural','Juridica') NOT NULL,
  `nombre_cliente` varchar(255) NOT NULL,
  `cedula` varchar(50) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `genero` enum('Masculino','Femenino','Otro') DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
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
  `nombre_empresa_negocio` varchar(255) DEFAULT NULL,
  `estabilidad_laboral` date DEFAULT NULL,
  `fecha_constitucion` date DEFAULT NULL,
  `marca_auto` varchar(100) DEFAULT NULL,
  `modelo_auto` varchar(100) DEFAULT NULL,
  `año_auto` int(11) DEFAULT NULL,
  `kilometraje` int(11) DEFAULT NULL,
  `precio_especial` decimal(15,2) DEFAULT NULL,
  `abono_porcentaje` decimal(5,2) DEFAULT NULL,
  `abono_monto` decimal(15,2) DEFAULT NULL,
  `comentarios_gestor` text DEFAULT NULL,
  `ejecutivo_banco` varchar(255) DEFAULT NULL,
  `respuesta_banco` enum('Pendiente','Aprobado','Pre Aprobado','Rechazado') DEFAULT 'Pendiente',
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

--
-- Volcado de datos para la tabla `solicitudes_credito`
--

INSERT INTO `solicitudes_credito` (`id`, `gestor_id`, `banco_id`, `vendedor_id`, `tipo_persona`, `nombre_cliente`, `cedula`, `edad`, `genero`, `telefono`, `email`, `direccion`, `provincia`, `distrito`, `corregimiento`, `barriada`, `casa_edif`, `numero_casa_apto`, `casado`, `hijos`, `perfil_financiero`, `ingreso`, `tiempo_laborar`, `nombre_empresa_negocio`, `estabilidad_laboral`, `fecha_constitucion`, `marca_auto`, `modelo_auto`, `año_auto`, `kilometraje`, `precio_especial`, `abono_porcentaje`, `abono_monto`, `comentarios_gestor`, `ejecutivo_banco`, `respuesta_banco`, `letra`, `plazo`, `abono_banco`, `promocion`, `comentarios_ejecutivo_banco`, `respuesta_cliente`, `motivo_respuesta`, `fecha_envio_proforma`, `fecha_firma_cliente`, `fecha_poliza`, `fecha_carta_promesa`, `comentarios_fi`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 3, NULL, NULL, 'Natural', 'Cesar Barria', 'PIPEDRIVE-66077390-fea7-11eb-b07e-39db335b2ae3', NULL, NULL, '+50765204216', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 66077390-fea7-11eb-b07e-39db335b2ae3, Persona ID: 67315)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 15:51:21', '2025-10-03 03:57:22'),
(2, 3, NULL, NULL, 'Natural', 'Anel vejas', 'PIPEDRIVE-67a55430-286c-11ec-b06e-fd2ffb0fe7e5', NULL, NULL, '+507 6402-6370', 'idisanais0404@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 67a55430-286c-11ec-b06e-fd2ffb0fe7e5, Persona ID: 70856)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:13', '2025-10-03 03:57:22'),
(3, 3, NULL, NULL, 'Natural', 'Marlei Concepcion', 'PIPEDRIVE-9dadbe10-ccbc-11ec-81da-998353b91306', 0, '', '', 'mc@futuroforestal.com', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: 9dadbe10-ccbc-11ec-81da-998353b91306, Persona ID: 83368)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'En Revisión Banco', '2025-09-15 17:59:15', '2025-10-03 04:27:59'),
(4, 1, NULL, NULL, 'Natural', 'Ing. Miguel Angel Ramos', 'PIPEDRIVE-702ae110-953f-11ed-919e-edff59e85713', NULL, NULL, '388-2466', 'priosapanama@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 702ae110-953f-11ed-919e-edff59e85713, Persona ID: 97658)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:16', '2025-09-15 17:59:16'),
(5, 1, NULL, NULL, 'Natural', 'Dashka Vaz', 'PIPEDRIVE-fdc98c60-d342-11ed-a9da-ff2ff8f98d9c', NULL, NULL, '+507 6677-2648', 'dashka.vaz@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: fdc98c60-d342-11ed-a9da-ff2ff8f98d9c, Persona ID: 62101)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:17', '2025-09-15 17:59:17'),
(6, 1, NULL, NULL, 'Natural', 'EDNA PAZ', 'PIPEDRIVE-99562570-d344-11ed-a9da-ff2ff8f98d9c', NULL, NULL, '50768424695', 'edna.paz61@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 99562570-d344-11ed-a9da-ff2ff8f98d9c, Persona ID: 101979)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:18', '2025-09-15 17:59:18'),
(7, 1, NULL, NULL, 'Natural', 'Michelle de la Guardia', 'PIPEDRIVE-fd2ee820-d344-11ed-94ba-e12b5499daf3', NULL, NULL, '66174343', 'michelle.delaguardia@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: fd2ee820-d344-11ed-94ba-e12b5499daf3, Persona ID: 101980)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:18', '2025-09-15 17:59:18'),
(8, 1, NULL, NULL, 'Natural', 'Ariel G', 'PIPEDRIVE-66fb9e30-d7b2-11ed-8c8e-db83f6d30aeb', NULL, NULL, '', 'daggpty214@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 66fb9e30-d7b2-11ed-8c8e-db83f6d30aeb, Persona ID: 102228)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:18', '2025-09-15 17:59:18'),
(9, 1, NULL, NULL, 'Natural', 'Daniel Maldonado', 'PIPEDRIVE-bf791320-e136-11ed-b425-87e40197cf1f', NULL, NULL, '65872700', 'danielmaldonadov@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: bf791320-e136-11ed-b425-87e40197cf1f, Persona ID: 103019)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:19', '2025-09-15 17:59:19'),
(10, 1, NULL, NULL, 'Natural', 'VALENTINA MONTEROS', 'PIPEDRIVE-1feafbb0-f320-11ed-a0ce-bf7b0ba715dc', NULL, NULL, '6622-7872', 'valentina.monteros@damen.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 1feafbb0-f320-11ed-a0ce-bf7b0ba715dc, Persona ID: 104033)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:19', '2025-09-15 17:59:19'),
(11, 1, NULL, NULL, 'Natural', 'MOHAMED PATEL', 'PIPEDRIVE-de72a8b0-f3e5-11ed-a0ce-bf7b0ba715dc', NULL, NULL, '6025-0124', 'mohamedp767@icloud.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: de72a8b0-f3e5-11ed-a0ce-bf7b0ba715dc, Persona ID: 103520)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:20', '2025-09-15 17:59:20'),
(12, 1, NULL, NULL, 'Natural', 'Eladio muñoz', 'PIPEDRIVE-5251f410-0a53-11ee-8dc8-875e9374713a', NULL, NULL, '+507 68804977', 'elalternador@cwpanama.net', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 5251f410-0a53-11ee-8dc8-875e9374713a, Persona ID: 77583)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:21', '2025-09-15 17:59:21'),
(13, 1, NULL, NULL, 'Natural', 'Gabriel Collin', 'PIPEDRIVE-c9c3c1f0-0afc-11ee-8262-853851f61b4a', NULL, NULL, '62047700', 'glito85@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: c9c3c1f0-0afc-11ee-8262-853851f61b4a, Persona ID: 107306)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:21', '2025-09-15 17:59:21'),
(14, 1, NULL, NULL, 'Natural', 'Mohamad Darwiche', 'PIPEDRIVE-184b40e0-0afe-11ee-8dc8-875e9374713a', NULL, NULL, '62418750', 'Elsafiro31@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 184b40e0-0afe-11ee-8dc8-875e9374713a, Persona ID: 106641)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:22', '2025-09-15 17:59:22'),
(15, 1, NULL, NULL, 'Natural', 'Marcos Broce Gonzalez', 'PIPEDRIVE-00bca510-0c64-11ee-921d-71f4c27663b8', NULL, NULL, '62-802070', 'marcosbroce@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 00bca510-0c64-11ee-921d-71f4c27663b8, Persona ID: 106744)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:22', '2025-09-15 17:59:22'),
(16, 1, NULL, NULL, 'Natural', 'Edwin García Pinto', 'PIPEDRIVE-bfdbe220-0cb0-11ee-8cad-0195906a7624', NULL, NULL, '65878171', 'Edwin.e.garcia@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: bfdbe220-0cb0-11ee-8cad-0195906a7624, Persona ID: 106776)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:23', '2025-09-15 17:59:23'),
(17, 1, NULL, NULL, 'Natural', 'Catalina Juárez', 'PIPEDRIVE-39f594f0-0d4e-11ee-921d-71f4c27663b8', NULL, NULL, '6417-1667', 'Juarezcathalina@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 39f594f0-0d4e-11ee-921d-71f4c27663b8, Persona ID: 106817)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:23', '2025-09-15 17:59:23'),
(18, 1, NULL, NULL, 'Natural', 'Gonzalo González', 'PIPEDRIVE-7f2c8010-0d58-11ee-b9b8-ef01b00bd05a', NULL, NULL, '66767161', 'g.gonzalez@platinum-insurance.net', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 7f2c8010-0d58-11ee-b9b8-ef01b00bd05a, Persona ID: 106830)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:24', '2025-09-15 17:59:24'),
(19, 1, NULL, NULL, 'Natural', 'Christian antonio Rodríguez', 'PIPEDRIVE-31dc6e70-0e01-11ee-88e7-73e117da9fb9', NULL, NULL, '69206841', 'chr2413@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 31dc6e70-0e01-11ee-88e7-73e117da9fb9, Persona ID: 106657)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:24', '2025-09-15 17:59:24'),
(20, 1, NULL, NULL, 'Natural', 'Axel Reseda', 'PIPEDRIVE-bc22e950-0e93-11ee-ad58-d97805092083', NULL, NULL, '63043548', 'axelreseda@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: bc22e950-0e93-11ee-ad58-d97805092083, Persona ID: 106865)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:24', '2025-09-15 17:59:24'),
(21, 1, NULL, NULL, 'Natural', 'Reyneiro ayarza', 'PIPEDRIVE-094c9d70-0ecb-11ee-857e-859b729f860e', NULL, NULL, '60869738', 'bandalife11@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 094c9d70-0ecb-11ee-857e-859b729f860e, Persona ID: 106907)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:25', '2025-09-15 17:59:25'),
(22, 1, NULL, NULL, 'Natural', 'Jowee figueroa', 'PIPEDRIVE-e5c993c0-0ed5-11ee-ba2a-65ff4717f7eb', NULL, NULL, '69465021', 'lastablas2014@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: e5c993c0-0ed5-11ee-ba2a-65ff4717f7eb, Persona ID: 106915)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:25', '2025-09-15 17:59:25'),
(23, 1, NULL, NULL, 'Natural', 'Florencio herrera', 'PIPEDRIVE-0672acc0-0f6b-11ee-ba2a-65ff4717f7eb', NULL, NULL, 'Correo', 'germanenriquefrancoariza@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 0672acc0-0f6b-11ee-ba2a-65ff4717f7eb, Persona ID: 106964)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:25', '2025-09-15 17:59:25'),
(24, 1, NULL, NULL, 'Natural', 'Austin', 'PIPEDRIVE-b82b2b80-1002-11ee-857e-859b729f860e', NULL, NULL, '66774378', 'Stivenaustinn@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: b82b2b80-1002-11ee-857e-859b729f860e, Persona ID: 107037)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:26', '2025-09-15 17:59:26'),
(25, 1, NULL, NULL, 'Natural', 'Jimena marchisio', 'PIPEDRIVE-16656b90-112d-11ee-9849-b5552ab5bf4e', NULL, NULL, 'Quiero saber una ide', 'Jmarchisio9@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 16656b90-112d-11ee-9849-b5552ab5bf4e, Persona ID: 107170)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:26', '2025-09-15 17:59:26'),
(26, 1, NULL, NULL, 'Natural', 'Pedro vasquezv', 'PIPEDRIVE-0b37b8b0-1149-11ee-92f1-f7ebe6180a32', NULL, NULL, '507-6644-1019', 'Papasitomio@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 0b37b8b0-1149-11ee-92f1-f7ebe6180a32, Persona ID: 107211)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:26', '2025-09-15 17:59:26'),
(27, 1, NULL, NULL, 'Natural', 'Julio Agudo', 'PIPEDRIVE-9e6d5d30-1174-11ee-a185-e11b1db0a4e3', NULL, NULL, '66516452', 'julioc0921@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 9e6d5d30-1174-11ee-a185-e11b1db0a4e3, Persona ID: 106113)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:27', '2025-09-15 17:59:27'),
(28, 1, NULL, NULL, 'Natural', 'Alonso Tejada Garrido', 'PIPEDRIVE-921e0050-11d5-11ee-aae0-654ead6221d0', NULL, NULL, '62271208', 'Alonso9130@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 921e0050-11d5-11ee-aae0-654ead6221d0, Persona ID: 98334)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:27', '2025-09-15 17:59:27'),
(29, 1, NULL, NULL, 'Natural', 'Edgar Diaz', 'PIPEDRIVE-c646b8e0-123e-11ee-92f1-f7ebe6180a32', NULL, NULL, '66742490', 'diaz.edgar78@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: c646b8e0-123e-11ee-92f1-f7ebe6180a32, Persona ID: 41493)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:28', '2025-09-15 17:59:28'),
(30, 1, NULL, NULL, 'Natural', 'Antonio Bianco', 'PIPEDRIVE-e095dbe0-12a7-11ee-9849-b5552ab5bf4e', NULL, NULL, '62150231', 'antoniobianco14@yahoo.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: e095dbe0-12a7-11ee-9849-b5552ab5bf4e, Persona ID: 107328)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:28', '2025-09-15 17:59:28'),
(31, 1, NULL, NULL, 'Natural', 'Angel yepez', 'PIPEDRIVE-9be74c10-12c8-11ee-aae0-654ead6221d0', NULL, NULL, '67850873', 'angelyepez221@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 9be74c10-12c8-11ee-aae0-654ead6221d0, Persona ID: 107356)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:28', '2025-09-15 17:59:28'),
(32, 1, NULL, NULL, 'Natural', 'Dora Coronado', 'PIPEDRIVE-157f31a0-139b-11ee-aae0-654ead6221d0', NULL, NULL, '66051645', 'doracoronado@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 157f31a0-139b-11ee-aae0-654ead6221d0, Persona ID: 107770)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:29', '2025-09-15 17:59:29'),
(33, 1, NULL, NULL, 'Natural', 'RICARDO LOPEZ', 'PIPEDRIVE-7d4ca120-142a-11ee-abec-f7d6bc219212', NULL, NULL, '', 'ricarlopec@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 7d4ca120-142a-11ee-abec-f7d6bc219212, Persona ID: 107444)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:29', '2025-09-15 17:59:29'),
(34, 1, NULL, NULL, 'Natural', 'Demetrio antonatos', 'PIPEDRIVE-73ee6e90-147c-11ee-a493-3f552a4cf134', NULL, NULL, '63178432', 'demetrioantonatos@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 73ee6e90-147c-11ee-a493-3f552a4cf134, Persona ID: 107429)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:29', '2025-09-15 17:59:29'),
(35, 1, NULL, NULL, 'Natural', 'Rodolfo santamaria', 'PIPEDRIVE-388de610-148a-11ee-a0d4-07a38bd6e17e', NULL, NULL, '+507 6948-8738', 'Servi.general@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 388de610-148a-11ee-a0d4-07a38bd6e17e, Persona ID: 33010)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:30', '2025-09-15 17:59:30'),
(36, 1, NULL, NULL, 'Natural', 'Luis Pertuz', 'PIPEDRIVE-aa674660-149d-11ee-abec-f7d6bc219212', NULL, NULL, '60379757', 'lpertuz@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: aa674660-149d-11ee-abec-f7d6bc219212, Persona ID: 107426)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:30', '2025-09-15 17:59:30'),
(37, 1, NULL, NULL, 'Natural', 'Johny Andrés Moreno solis', 'PIPEDRIVE-8a1b1a90-45c5-11ee-a8eb-f1d79ab836e5', NULL, NULL, '6873-2991', 'alessandraiturralde18@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 8a1b1a90-45c5-11ee-a8eb-f1d79ab836e5, Persona ID: 111835)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:32', '2025-09-15 17:59:32'),
(38, 1, NULL, NULL, 'Natural', 'Edwin Cisneros', 'PIPEDRIVE-abfe7ec0-639a-11ee-8767-9122f4f2df4c', NULL, NULL, '68153665', 'e.cisneros@nke.at', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: abfe7ec0-639a-11ee-8767-9122f4f2df4c, Persona ID: 114629)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:33', '2025-09-15 17:59:33'),
(39, 1, NULL, NULL, 'Natural', 'Ernesto Serrano', 'PIPEDRIVE-6e862240-66a7-11ee-a788-e1593d9560fa', NULL, NULL, '507 ', 'eisaac.1487@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 6e862240-66a7-11ee-a788-e1593d9560fa, Persona ID: 114918)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:33', '2025-09-15 17:59:33'),
(40, 1, NULL, NULL, 'Natural', 'pablo', 'PIPEDRIVE-f1e94810-7da4-11ee-982c-fdb71ce06650', NULL, NULL, '', 'paraujoriestra@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: f1e94810-7da4-11ee-982c-fdb71ce06650, Persona ID: 116906)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:34', '2025-09-15 17:59:34'),
(41, 1, NULL, NULL, 'Natural', 'eric ortiz', 'PIPEDRIVE-60962830-894b-11ee-8d3a-5da99078d4bd', NULL, NULL, '6515-6862', 'Eortiz@coscopan.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 60962830-894b-11ee-8d3a-5da99078d4bd, Persona ID: 117645)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:34', '2025-09-15 17:59:34'),
(42, 1, NULL, NULL, 'Natural', 'Javier', 'PIPEDRIVE-8e57e250-a69b-11ee-a103-fd974c612644', NULL, NULL, '', 'bquero36@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 8e57e250-a69b-11ee-a103-fd974c612644, Persona ID: 120006)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:36', '2025-09-15 17:59:36'),
(43, 1, NULL, NULL, 'Natural', 'Roberto Marín', 'PIPEDRIVE-da8e9390-c260-11ee-97c8-4f81561fa2a6', NULL, NULL, '', 'rmarin2026@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: da8e9390-c260-11ee-97c8-4f81561fa2a6, Persona ID: 122246)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:37', '2025-09-15 17:59:37'),
(44, 1, NULL, NULL, 'Natural', 'R', 'PIPEDRIVE-ecdd6780-ccf9-11ee-81a0-9fa0eb8615f9', NULL, NULL, '', 'rubielaargenis@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: ecdd6780-ccf9-11ee-81a0-9fa0eb8615f9, Persona ID: 123152)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:38', '2025-09-15 17:59:38'),
(45, 1, NULL, NULL, 'Natural', 'English', 'PIPEDRIVE-dedbbc90-d285-11ee-8851-b36834437b13', NULL, NULL, '69211636', 'DLOVELL1956@HOTMAIL.COM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: dedbbc90-d285-11ee-8851-b36834437b13, Persona ID: 123695)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:38', '2025-09-15 17:59:38'),
(46, 1, NULL, NULL, 'Natural', 'Jose Mimel', 'PIPEDRIVE-792ccb10-d8ab-11ee-a158-67f5a0dfbafe', NULL, NULL, '68991477', 'perrugo01763@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 792ccb10-d8ab-11ee-a158-67f5a0dfbafe, Persona ID: 124236)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:40', '2025-09-15 17:59:40'),
(47, 1, NULL, NULL, 'Natural', 'Juan Caraballo', 'PIPEDRIVE-d7c65290-d8ab-11ee-8ece-bf121313c792', NULL, NULL, '64096660', 'desotec06@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: d7c65290-d8ab-11ee-8ece-bf121313c792, Persona ID: 124238)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:40', '2025-09-15 17:59:40'),
(48, 1, NULL, NULL, 'Natural', 'Jonathan Bordonez', 'PIPEDRIVE-22a36d80-d8b5-11ee-a158-67f5a0dfbafe', NULL, NULL, '68826829', 'bordonezjhonny@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 22a36d80-d8b5-11ee-a158-67f5a0dfbafe, Persona ID: 124252)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:40', '2025-09-15 17:59:40'),
(49, 1, NULL, NULL, 'Natural', 'Héctor Arancibia', 'PIPEDRIVE-83cea6f0-d8b6-11ee-9c94-3d58d8953fd1', NULL, NULL, '67030032', 'aranbiah1230@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 83cea6f0-d8b6-11ee-9c94-3d58d8953fd1, Persona ID: 124253)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:41', '2025-09-15 17:59:41'),
(50, 1, NULL, NULL, 'Natural', 'Carmen Carvajal', 'PIPEDRIVE-c342b380-d8b6-11ee-a1c6-d520d4ee68dd', NULL, NULL, '68718360', 'carvajal1025@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: c342b380-d8b6-11ee-a1c6-d520d4ee68dd, Persona ID: 124254)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:41', '2025-09-15 17:59:41'),
(51, 1, NULL, NULL, 'Natural', 'Eric Rodriguez', 'PIPEDRIVE-3f82de20-d8b7-11ee-a158-67f5a0dfbafe', NULL, NULL, '+507 6573-2598', 'er4166028@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 3f82de20-d8b7-11ee-a158-67f5a0dfbafe, Persona ID: 96234)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:41', '2025-09-15 17:59:41'),
(52, 1, NULL, NULL, 'Natural', 'Milagros Gamboa', 'PIPEDRIVE-803e8580-dfc0-11ee-b9f3-9963250f3474', NULL, NULL, '', 'mila_gamboa14@hotmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 803e8580-dfc0-11ee-b9f3-9963250f3474, Persona ID: 124859)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:42', '2025-09-15 17:59:42'),
(53, 1, NULL, NULL, 'Natural', 'CESAL JIMENEZ', 'PIPEDRIVE-42308790-e13f-11ee-9c0f-5167a00fafa8', NULL, NULL, '6250-5570', 'celsadeescalona@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: 42308790-e13f-11ee-9c0f-5167a00fafa8, Persona ID: 125203)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:43', '2025-09-15 17:59:43'),
(54, 1, NULL, NULL, 'Natural', 'Tania Salazar Botero', 'PIPEDRIVE-c726c7b0-e53c-11ee-89b8-fb6f82b5a6f1', NULL, NULL, '3054195617', 'taniasalazarbotero@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'Asalariado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Lead importado desde Pipedrive (Lead ID: c726c7b0-e53c-11ee-89b8-fb6f82b5a6f1, Persona ID: 125367)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Nueva', '2025-09-15 17:59:44', '2025-09-15 17:59:44'),
(55, 1, NULL, 3, 'Natural', 'HAMILTO LARA', 'PIPEDRIVE-f1105940-e557-11ee-843f-cb415d249d93', 0, '', '6833-1322', 'HLARA0289@GMAIL.COM', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: f1105940-e557-11ee-843f-cb415d249d93, Persona ID: 125398)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Aprobada', '2025-09-15 17:59:45', '2025-10-08 17:15:49'),
(56, 1, NULL, 3, 'Natural', 'JORGE LOPEZ', 'PIPEDRIVE-cbb6f9c0-eac4-11ee-8856-2769ed5f7e76', 0, '', '2237482', 'jorge_lopez@tcc.com.pa', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: cbb6f9c0-eac4-11ee-8856-2769ed5f7e76, Persona ID: 3186)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'En Revisión Banco', '2025-09-15 17:59:46', '2025-10-08 16:57:25'),
(57, 1, NULL, NULL, 'Natural', 'Miguel herrers', 'PIPEDRIVE-110b4750-ee45-11ee-8bbe-03378b814a49', 0, '', '', 'hmiguelalexander@yahoo.es', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: 110b4750-ee45-11ee-8bbe-03378b814a49, Persona ID: 126248)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Rechazada', '2025-09-15 17:59:46', '2025-10-08 16:56:44'),
(58, 1, NULL, NULL, 'Natural', 'Gabriel Dobras', 'PIPEDRIVE-f44fa2d0-faad-11ee-8e1e-5384cb290fce', 0, '', '', 'dobrasa@hotmail.com', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: f44fa2d0-faad-11ee-8e1e-5384cb290fce, Persona ID: 127344)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'Aprobada', '2025-09-15 17:59:48', '2025-10-01 04:42:39'),
(59, 1, NULL, NULL, 'Natural', 'Dante Rodriguez', 'PIPEDRIVE-0959c9e0-fcba-11ee-b965-95cb52700b70', 0, '', '', 'danruro@gmail.com', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: 0959c9e0-fcba-11ee-b965-95cb52700b70, Persona ID: 127536)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 'En Revisión Banco', '2025-09-15 17:59:48', '2025-10-01 03:50:32'),
(60, 1, NULL, NULL, 'Natural', 'Jose ramirez', 'PIPEDRIVE-83cd3c10-01a2-11ef-bf74-8df91e30e078', 0, '', '65765526', 'Josemiguelramirez117@gmail.com', '', '', '', '', '', '', '', 0, 0, 'Asalariado', 0.00, '', '', '0000-00-00', '0000-00-00', '', '', 0, 0, 0.00, 0.00, 0.00, 'Lead importado desde Pipedrive (Lead ID: 83cd3c10-01a2-11ef-bf74-8df91e30e078, Persona ID: 127983)', NULL, 'Pendiente', NULL, NULL, NULL, NULL, NULL, 'Rechaza', 'Prueba de funcionalidad del sistema', NULL, NULL, NULL, NULL, NULL, 'Desistimiento', '2025-09-15 17:59:49', '2025-10-01 04:30:50');

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
(3, 'Carlos', 'Gestor', 'gestor@sistema.com', '$2y$10$KPA906Oaj5Dh4wDBnSqNi.nuQ9ZxRm4kQvoU0KBe1YO1hGHhFiili', 'México', 'vendedor', NULL, '555-0002', NULL, NULL, 1, 0, '2025-09-29 19:35:04', '2025-10-01 05:50:34');

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

--
-- Volcado de datos para la tabla `usuarios_banco_solicitudes`
--

INSERT INTO `usuarios_banco_solicitudes` (`id`, `solicitud_id`, `usuario_banco_id`, `estado`, `fecha_asignacion`, `fecha_desactivacion`, `creado_por`) VALUES
(9, 60, 2, 'activo', '2025-10-01 03:49:18', NULL, 1),
(10, 59, 2, 'activo', '2025-10-01 03:50:32', NULL, 1),
(12, 58, 2, 'activo', '2025-10-03 03:34:59', NULL, 1),
(13, 3, 2, 'activo', '2025-10-03 04:27:43', NULL, 3),
(14, 57, 2, 'activo', '2025-10-08 16:56:03', NULL, 1),
(15, 56, 2, 'activo', '2025-10-08 16:57:25', NULL, 1);

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
(20, 3, 7, '2025-10-03 04:25:43');

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
  ADD KEY `idx_fecha` (`fecha_creacion`);

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
  ADD KEY `idx_vendedor` (`vendedor_id`);

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
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adjuntos_solicitud`
--
ALTER TABLE `adjuntos_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `bancos`
--
ALTER TABLE `bancos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- AUTO_INCREMENT de la tabla `mensajes_solicitud`
--
ALTER TABLE `mensajes_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `notas_solicitud`
--
ALTER TABLE `notas_solicitud`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `solicitudes_credito`
--
ALTER TABLE `solicitudes_credito`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios_banco_solicitudes`
--
ALTER TABLE `usuarios_banco_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `usuario_roles`
--
ALTER TABLE `usuario_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
-- Filtros para la tabla `mensajes_solicitud`
--
ALTER TABLE `mensajes_solicitud`
  ADD CONSTRAINT `mensajes_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensajes_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notas_solicitud`
--
ALTER TABLE `notas_solicitud`
  ADD CONSTRAINT `notas_solicitud_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_credito` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notas_solicitud_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_credito`
--
ALTER TABLE `solicitudes_credito`
  ADD CONSTRAINT `fk_solicitudes_banco` FOREIGN KEY (`banco_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_solicitudes_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `solicitudes_credito_ibfk_1` FOREIGN KEY (`gestor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
