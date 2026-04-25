-- Telemetria del formulario publico de financiamiento
-- Captura tiempos por paso, inicio/fin, eventos y datos de dispositivo

ALTER TABLE inanciamiento_registros
  ADD COLUMN IF NOT EXISTS 	elemetria_session_id VARCHAR(100) NULL AFTER irmantes_adicionales,
  ADD COLUMN IF NOT EXISTS 	elemetria_started_at DATETIME NULL AFTER 	elemetria_session_id,
  ADD COLUMN IF NOT EXISTS 	elemetria_submitted_at DATETIME NULL AFTER 	elemetria_started_at,
  ADD COLUMN IF NOT EXISTS 	elemetria_duracion_segundos INT(11) NULL AFTER 	elemetria_submitted_at,
  ADD COLUMN IF NOT EXISTS 	elemetria_paso_tiempos_json LONGTEXT NULL AFTER 	elemetria_duracion_segundos,
  ADD COLUMN IF NOT EXISTS 	elemetria_eventos_json LONGTEXT NULL AFTER 	elemetria_paso_tiempos_json,
  ADD COLUMN IF NOT EXISTS 	elemetria_dispositivo_json LONGTEXT NULL AFTER 	elemetria_eventos_json;
