-- Score APC en la pestaña Análisis de solicitudes de crédito.
ALTER TABLE solicitudes_credito
  ADD COLUMN score_apc DECIMAL(10,2) NULL DEFAULT NULL
  COMMENT 'Score APC del cliente (pestaña Análisis)'
  AFTER comentarios_gestor;
