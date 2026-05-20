-- Vincula registros históricos al catálogo ejecutivos_ventas usando el correo del vendedor.
-- Caso típico: ejecutivos BDC creados hoy, pero Sol. Financiamiento / crédito ya tenían solo email_vendedor.
--
-- IMPORTANTE: Ejecutar primero los SELECT (paso 1) y revisar cantidades antes de los UPDATE.
-- Hacer backup o probar en copia de la base si es producción.

-- ---------------------------------------------------------------------------
-- 1) PREVISUALIZACIÓN (solo lectura)
-- ---------------------------------------------------------------------------

-- Financiamiento sin id_vendedor pero con email que ya existe en ejecutivos_ventas
SELECT fr.id, fr.fecha_creacion, fr.email_vendedor, ev.id AS ejecutivo_id, ev.nombre, ev.sucursal
FROM financiamiento_registros fr
INNER JOIN ejecutivos_ventas ev
  ON LOWER(TRIM(fr.email_vendedor)) = LOWER(TRIM(ev.email))
WHERE (fr.id_vendedor IS NULL OR fr.id_vendedor = 0)
  AND fr.email_vendedor IS NOT NULL
  AND TRIM(fr.email_vendedor) <> ''
-- AND UPPER(TRIM(ev.sucursal)) = 'BDC'   -- opcional: solo BDC
ORDER BY fr.fecha_creacion DESC;

-- Solicitudes de crédito sin ejecutivo, enlazables vía financiamiento_registros.email_vendedor
SELECT s.id, s.nombre_cliente, s.estado, fr.email_vendedor, ev.id AS ejecutivo_id, ev.sucursal
FROM solicitudes_credito s
INNER JOIN financiamiento_registros fr
  ON fr.id = s.financiamiento_registro_id
  OR fr.solicitud_credito_id = s.id
INNER JOIN ejecutivos_ventas ev
  ON LOWER(TRIM(fr.email_vendedor)) = LOWER(TRIM(ev.email))
WHERE (s.ejecutivo_ventas_id IS NULL OR s.ejecutivo_ventas_id = 0)
  AND fr.email_vendedor IS NOT NULL
  AND TRIM(fr.email_vendedor) <> ''
-- AND UPPER(TRIM(ev.sucursal)) = 'BDC'
ORDER BY s.id DESC;

-- ---------------------------------------------------------------------------
-- 2) ACTUALIZAR financiamiento_registros.id_vendedor por email
--    (usa el menor id si hubiera correos duplicados en ejecutivos_ventas)
-- ---------------------------------------------------------------------------

UPDATE financiamiento_registros fr
INNER JOIN (
    SELECT LOWER(TRIM(email)) AS email_norm, MIN(id) AS ev_id
    FROM ejecutivos_ventas
    WHERE email IS NOT NULL AND TRIM(email) <> ''
    GROUP BY LOWER(TRIM(email))
) ev ON LOWER(TRIM(fr.email_vendedor)) = ev.email_norm
SET fr.id_vendedor = ev.ev_id
WHERE (fr.id_vendedor IS NULL OR fr.id_vendedor = 0)
  AND fr.email_vendedor IS NOT NULL
  AND TRIM(fr.email_vendedor) <> '';
-- Para limitar solo BDC, descomente y ajuste el JOIN:
-- INNER JOIN ejecutivos_ventas evb ON evb.id = ev.ev_id AND UPPER(TRIM(evb.sucursal)) = 'BDC'

-- ---------------------------------------------------------------------------
-- 3) ACTUALIZAR solicitudes_credito.ejecutivo_ventas_id
--    a) desde id_vendedor ya rellenado en financiamiento
-- ---------------------------------------------------------------------------

UPDATE solicitudes_credito s
INNER JOIN financiamiento_registros fr ON fr.id = s.financiamiento_registro_id
SET s.ejecutivo_ventas_id = fr.id_vendedor
WHERE (s.ejecutivo_ventas_id IS NULL OR s.ejecutivo_ventas_id = 0)
  AND fr.id_vendedor IS NOT NULL
  AND fr.id_vendedor > 0;

UPDATE solicitudes_credito s
INNER JOIN financiamiento_registros fr ON fr.solicitud_credito_id = s.id
SET s.ejecutivo_ventas_id = fr.id_vendedor
WHERE (s.ejecutivo_ventas_id IS NULL OR s.ejecutivo_ventas_id = 0)
  AND fr.id_vendedor IS NOT NULL
  AND fr.id_vendedor > 0;

--    b) por email directo (si aún falta vínculo)
-- ---------------------------------------------------------------------------

UPDATE solicitudes_credito s
INNER JOIN financiamiento_registros fr
  ON fr.id = s.financiamiento_registro_id OR fr.solicitud_credito_id = s.id
INNER JOIN (
    SELECT LOWER(TRIM(email)) AS email_norm, MIN(id) AS ev_id
    FROM ejecutivos_ventas
    WHERE email IS NOT NULL AND TRIM(email) <> ''
    GROUP BY LOWER(TRIM(email))
) ev ON LOWER(TRIM(fr.email_vendedor)) = ev.email_norm
SET s.ejecutivo_ventas_id = ev.ev_id
WHERE (s.ejecutivo_ventas_id IS NULL OR s.ejecutivo_ventas_id = 0)
  AND fr.email_vendedor IS NOT NULL
  AND TRIM(fr.email_vendedor) <> '';

-- ---------------------------------------------------------------------------
-- 4) VERIFICACIÓN posterior
-- ---------------------------------------------------------------------------

SELECT COUNT(*) AS fin_sin_vendedor
FROM financiamiento_registros
WHERE (id_vendedor IS NULL OR id_vendedor = 0)
  AND email_vendedor IS NOT NULL AND TRIM(email_vendedor) <> '';

SELECT COUNT(*) AS credito_sin_ejecutivo
FROM solicitudes_credito
WHERE ejecutivo_ventas_id IS NULL OR ejecutivo_ventas_id = 0;
