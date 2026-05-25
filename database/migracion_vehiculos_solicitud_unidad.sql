-- Unidad de inventario (Excel reservas col. AF) en vehículos de la solicitud.
-- Ejecutar una sola vez en producción/local.

ALTER TABLE `vehiculos_solicitud`
  ADD COLUMN `unidad` varchar(80) DEFAULT NULL AFTER `abono_monto`;
