-- Criterio / motivo de aceptación al seleccionar una propuesta bancaria.
-- Ejecutar en motus_baes (o la BD de la app).

SET NAMES utf8mb4;

ALTER TABLE `solicitudes_credito`
  ADD COLUMN `criterio_seleccion_propuesta` varchar(120) DEFAULT NULL
    COMMENT 'Motivo de aceptación de la propuesta (select obligatorio)'
    AFTER `comentario_seleccion_propuesta`;
