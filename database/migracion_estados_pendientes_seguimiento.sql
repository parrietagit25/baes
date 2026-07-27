-- Estados operativos de seguimiento (solo etiqueta; sin lógica especial).
-- La columna estado ya es VARCHAR(64); este archivo documenta los valores nuevos.

-- Valores permitidos vía Cambiar Estado (gestor/admin):
-- 'Pend. Firma'
-- 'Pend. Poliza'
-- 'Pend. Abono'
-- 'Pend. Abono y poliza'
-- 'Pend. CPP'

SELECT 'OK: estados Pend. listos (VARCHAR, sin ALTER requerido)' AS resultado;
