-- Geolocalizacion por IP persistida en el registro del formulario publico.
-- El reporte de telemetria usa estos campos si existen y evita llamadas repetidas a ipwho.is.
-- Ejecutar una sola vez en la base donde esta financiamiento_registros (ej. motus_baes).
-- Si la columna ya existe, MySQL devolvera error 1060 Duplicate column (ignorar o comentar la linea correspondiente).

ALTER TABLE `financiamiento_registros`
  ADD COLUMN `telemetria_geo_country` VARCHAR(120) NULL DEFAULT NULL COMMENT 'Pais por geolocalizacion IP (persistido)' AFTER `telemetria_dispositivo_json`;

ALTER TABLE `financiamiento_registros`
  ADD COLUMN `telemetria_geo_city` VARCHAR(120) NULL DEFAULT NULL COMMENT 'Ciudad por geolocalizacion IP (persistido)' AFTER `telemetria_geo_country`;
