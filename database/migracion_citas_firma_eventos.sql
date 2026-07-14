-- Eventos en Cita y Firma: nombre del evento + fecha + comentario.
-- Ejecutar una sola vez. Si la columna ya existe, ignore Duplicate column.

ALTER TABLE `citas_firma`
  ADD COLUMN `nombre_evento` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nombre del evento'
  AFTER `solicitud_id`;

ALTER TABLE `citas_firma`
  MODIFY `hora_cita` TIME NULL DEFAULT NULL;
