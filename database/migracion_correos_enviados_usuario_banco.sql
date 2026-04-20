-- Contador de correos enviados al usuario banco por asignación (solicitud + usuario banco).
-- Ejecutar una vez en la base activa (p. ej. solicitud_credito o motus_baes).

ALTER TABLE `usuarios_banco_solicitudes`
  ADD COLUMN `correos_enviados` int UNSIGNED NOT NULL DEFAULT 0
  AFTER `creado_por`;
