-- Líneas del Excel Proforma / Reservas y marcado de vehículo apartado.
-- Ejecutar después de migracion_reportes_reservas.sql

ALTER TABLE `reportes_reservas`
  ADD COLUMN `estado` enum('pendiente','procesando','completado','error') NOT NULL DEFAULT 'pendiente' AFTER `usuario_id`,
  ADD COLUMN `filas_total` int unsigned NOT NULL DEFAULT 0 AFTER `estado`,
  ADD COLUMN `filas_aplicadas` int unsigned NOT NULL DEFAULT 0 AFTER `filas_total`,
  ADD COLUMN `filas_sin_coincidencia` int unsigned NOT NULL DEFAULT 0 AFTER `filas_aplicadas`,
  ADD COLUMN `fecha_procesado` timestamp NULL DEFAULT NULL AFTER `filas_sin_coincidencia`;

CREATE TABLE IF NOT EXISTS `reportes_reservas_lineas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `fila_excel` int NOT NULL,
  `mov` varchar(80) DEFAULT NULL,
  `mov_id` varchar(80) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `dias_reserva` int DEFAULT NULL,
  `nombre_sucursal` varchar(255) DEFAULT NULL,
  `nombre_vendedor` varchar(255) DEFAULT NULL,
  `cliente_codigo` varchar(80) DEFAULT NULL,
  `nombre_cliente` varchar(255) DEFAULT NULL,
  `cedula` varchar(80) DEFAULT NULL,
  `cedula_norm` varchar(80) DEFAULT NULL,
  `correo_cliente` varchar(255) DEFAULT NULL,
  `correo_norm` varchar(255) DEFAULT NULL,
  `marca` varchar(120) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `anio` int DEFAULT NULL,
  `kilometraje` int DEFAULT NULL,
  `precio_total` decimal(15,2) DEFAULT NULL,
  `abono_monto` decimal(15,2) DEFAULT NULL,
  `abono_porcentaje` decimal(5,2) DEFAULT NULL,
  `unidad` varchar(80) DEFAULT NULL,
  `chasis` varchar(80) DEFAULT NULL,
  `placas` varchar(40) DEFAULT NULL,
  `solicitud_id` int DEFAULT NULL,
  `vehiculo_id` int DEFAULT NULL,
  `match_por` enum('cedula','email','nombre','ninguno') DEFAULT 'ninguno',
  `estado` enum('pendiente','aplicado','sin_coincidencia','error') NOT NULL DEFAULT 'pendiente',
  `mensaje` varchar(500) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rr_lineas_reporte` (`reporte_id`),
  KEY `idx_rr_lineas_solicitud` (`solicitud_id`),
  KEY `idx_rr_lineas_cedula_norm` (`cedula_norm`),
  KEY `idx_rr_lineas_correo_norm` (`correo_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `vehiculos_solicitud`
  ADD COLUMN `apartado` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `apartado_en` timestamp NULL DEFAULT NULL,
  ADD COLUMN `mov_id_reserva` varchar(80) DEFAULT NULL;
