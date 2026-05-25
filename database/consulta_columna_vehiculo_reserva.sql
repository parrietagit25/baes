-- Columna Vehículo en solicitudes: solo aplica si hay reserva procesada (estado aplicado).
-- Ejecutar para revisar qué solicitudes mostrarán texto en la lista.

SELECT
  s.id AS solicitud_id,
  s.nombre_cliente,
  CASE
    WHEN EXISTS (
      SELECT 1 FROM reportes_reservas_lineas r
      WHERE r.solicitud_id = s.id AND r.estado = 'aplicado'
    ) THEN TRIM(CONCAT_WS(
      ' — ',
      NULLIF(TRIM(v.unidad), ''),
      NULLIF(TRIM(CONCAT_WS(' ', v.marca, v.modelo, v.anio)), '')
    ))
    ELSE ''
  END AS texto_columna_vehiculo,
  v.unidad,
  v.marca,
  v.modelo,
  v.anio,
  (SELECT COUNT(*) FROM reportes_reservas_lineas r
   WHERE r.solicitud_id = s.id AND r.estado = 'aplicado') AS reservas_aplicadas
FROM solicitudes_credito s
LEFT JOIN vehiculos_solicitud v ON v.id = (
  SELECT v2.id
  FROM vehiculos_solicitud v2
  WHERE v2.solicitud_id = s.id
  ORDER BY v2.orden ASC, v2.id ASC
  LIMIT 1
)
ORDER BY s.id DESC
LIMIT 100;

-- Solo solicitudes CON reserva aplicada (las que verán texto en columna Vehículo):
-- SELECT * FROM (
--   ... query anterior ...
-- ) t WHERE t.reservas_aplicadas > 0;
