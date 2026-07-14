-- Estados de solicitud como etiqueta libre (incluye Evaluation/Comité/etc.).
-- Evita problemas de ENUM con acentos al ampliar valores.

ALTER TABLE `solicitudes_credito`
  MODIFY COLUMN `estado` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nueva';
