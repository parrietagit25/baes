-- Telemetria del formulario publico de financiamiento
-- Captura tiempos por paso, inicio/fin, eventos y datos de dispositivo

ALTER TABLE `financiamiento_registros`
  ADD COLUMN IF NOT EXISTS `telemetria_session_id` VARCHAR(100) NULL AFTER `firmantes_adicionales`,
  ADD COLUMN IF NOT EXISTS `telemetria_started_at` DATETIME NULL AFTER `telemetria_session_id`,
  ADD COLUMN IF NOT EXISTS `telemetria_submitted_at` DATETIME NULL AFTER `telemetria_started_at`,
  ADD COLUMN IF NOT EXISTS `telemetria_duracion_segundos` INT(11) NULL AFTER `telemetria_submitted_at`,
  ADD COLUMN IF NOT EXISTS `telemetria_paso_tiempos_json` LONGTEXT NULL AFTER `telemetria_duracion_segundos`,
  ADD COLUMN IF NOT EXISTS `telemetria_eventos_json` LONGTEXT NULL AFTER `telemetria_paso_tiempos_json`,
  ADD COLUMN IF NOT EXISTS `telemetria_dispositivo_json` LONGTEXT NULL AFTER `telemetria_eventos_json`;
