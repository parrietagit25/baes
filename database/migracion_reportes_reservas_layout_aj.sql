-- Layout Excel A–AJ: upsert global por Mov ID (mov_id_norm).
-- Ejecutar después de migracion_reportes_reservas_lineas.sql

ALTER TABLE `reportes_reservas_lineas`
  ADD COLUMN `mov_id_norm` varchar(80) DEFAULT NULL AFTER `mov_id`,
  ADD COLUMN `datos_excel_json` json DEFAULT NULL AFTER `placas`;

ALTER TABLE `reportes_reservas_lineas`
  ADD UNIQUE KEY `uq_rr_lineas_mov_id_norm` (`mov_id_norm`);
