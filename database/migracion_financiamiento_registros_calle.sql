-- Campo opcional calle en registros de formulario publico
-- Ejecutar en la base donde exista financiamiento_registros

ALTER TABLE `financiamiento_registros`
ADD COLUMN IF NOT EXISTS `calle` VARCHAR(120) NULL AFTER `barriada_calle_casa`;
