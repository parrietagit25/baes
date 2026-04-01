-- Comentario del gestor/admin al solicitar reevaluación (visible para el banco en «Ver mis respuestas»)
ALTER TABLE `evaluaciones_banco`
  ADD COLUMN `comentario_reevaluacion_solicitada` TEXT NULL DEFAULT NULL COMMENT 'Motivo indicado al pedir reevaluación' AFTER `comentarios`,
  ADD COLUMN `fecha_solicitud_reevaluacion` DATETIME NULL DEFAULT NULL AFTER `comentario_reevaluacion_solicitada`;
