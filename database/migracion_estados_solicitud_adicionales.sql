-- Ampliar enum de estado en solicitudes_credito (solo etiquetas operativas).
-- Ejecutar una sola vez.

ALTER TABLE `solicitudes_credito`
  MODIFY COLUMN `estado` ENUM(
    'Nueva',
    'En Revisión Banco',
    'Aprobada',
    'Rechazada',
    'Completada',
    'Desistimiento',
    'Evaluacion',
    'Comité',
    'Reconsideración',
    'Pre Aprobado',
    'Aprobado con Condición'
  ) COLLATE utf8mb4_unicode_ci DEFAULT 'Nueva';
