-- Vincula cada registro del formulario público con la solicitud_credito creada en Motus (misma base).
-- Permite contar y copiar adjuntos al crear una nueva solicitud desde "Cargar desde Sol Financiamiento"
-- sin asignar financiamiento_registro_id a la solicitud del formulario (evita colisión con uq_solicitudes_financiamiento_registro).

ALTER TABLE financiamiento_registros
  ADD COLUMN solicitud_credito_id INT NULL DEFAULT NULL
  COMMENT 'ID en solicitudes_credito generada desde este envío (p. ej. formulario público)';

ALTER TABLE financiamiento_registros
  ADD KEY idx_fin_reg_solicitud_credito (solicitud_credito_id);
