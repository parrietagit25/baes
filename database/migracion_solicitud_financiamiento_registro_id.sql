-- Vincula solicitudes_credito con financiamiento_registros (Sol Financiamiento).
-- Un mismo registro de financiamiento no puede asignarse a dos solicitudes.
-- Ejecutar una vez en la misma base donde están ambas tablas (p. ej. motus_baes).

ALTER TABLE solicitudes_credito
  ADD COLUMN financiamiento_registro_id INT NULL DEFAULT NULL
  COMMENT 'ID en financiamiento_registros si la solicitud se cargó desde Sol Financiamiento';

ALTER TABLE solicitudes_credito
  ADD UNIQUE KEY uq_solicitudes_financiamiento_registro (financiamiento_registro_id);
