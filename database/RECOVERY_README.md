# Recuperación operativa de `solicitudes_credito`

Procedimiento por fases para reconstruir solicitudes faltantes a partir de tablas relacionadas.
Toda la lógica está en archivos SQL: solo se ejecutan, no se modifican.

## Pre-requisitos
- Acceso al servidor con `motus_db` corriendo en Docker.
- Variable `DBIP` opcional (no usada en estos scripts; todo va por `docker exec`).

## Backups previos al swap
Antes de la Fase 4 (swap a producción), tomar respaldo:

```bash
docker exec -i motus_db mysqldump -uroot -p'motus_root_2024' \
  --single-transaction --routines --triggers \
  motus_baes solicitudes_credito \
  > /root/backup_solicitudes_credito_pre_recovery_$(date +%F_%H%M).sql
```

## Fase 1: inventario (solo lectura)

```bash
docker exec -i motus_db mysql -uroot -p'motus_root_2024' --default-character-set=utf8mb4 -t \
  < /root/baes/database/recovery_fase1_inventario.sql \
  > /root/recovery_fase1_resultado.txt 2>&1

less /root/recovery_fase1_resultado.txt
```

Revisar:
- Total de IDs faltantes vs IDs en producción.
- Cobertura por tabla relacionada.
- Pistas de gestor/banco inferidos.

Si el total de IDs faltantes es 0, no hay nada que reconstruir y el proceso termina aquí.

## Fase 3: staging y reconstrucción (en `motus_baes_recovery`)

```bash
docker exec -i motus_db mysql -uroot -p'motus_root_2024' --default-character-set=utf8mb4 -t \
  < /root/baes/database/recovery_fase3_staging.sql \
  > /root/recovery_fase3_resultado.txt 2>&1

less /root/recovery_fase3_resultado.txt
```

Revisar todas las secciones `== VALIDACION: ... ==`:
- IDs duplicados: debe ser 0 filas.
- Colisión con producción: debe ser 0 filas.
- NOT NULL críticos: todas las columnas en 0.
- Estados/respuesta_banco fuera de ENUM: 0 filas.

Si alguna validación falla, revisar antes de continuar.

## Fase 4: swap a producción

```bash
docker exec -i motus_db mysql -uroot -p'motus_root_2024' --default-character-set=utf8mb4 -t \
  < /root/baes/database/recovery_fase4_swap.sql \
  > /root/recovery_fase4_resultado.txt 2>&1

less /root/recovery_fase4_resultado.txt
```

Revisar:
- Pre-check de colisiones: debe imprimir 0.
- Total final en producción debe ser `60 + reconstruidas`.
- AUTO_INCREMENT debe quedar en `MAX(id)+1`.

## Fase 5: validación post-recuperación

```bash
docker exec -i motus_db mysql -uroot -p'motus_root_2024' --default-character-set=utf8mb4 -t \
  < /root/baes/database/recovery_fase5_validacion.sql \
  > /root/recovery_fase5_resultado.txt 2>&1

less /root/recovery_fase5_resultado.txt
```

Y validación funcional en UI:
- Abrir `solicitudes.php` y confirmar listado completo.
- Abrir un detalle de una solicitud reconstruida (cédula `RECUPERADO-{id}`).
- Crear una solicitud nueva y verificar que tome el siguiente ID disponible.

## Rollback (si algo sale mal después del swap)

```bash
docker exec -i motus_db mysql -uroot -p'motus_root_2024' \
  motus_baes < /root/backup_solicitudes_credito_pre_recovery_<timestamp>.sql
```

## Mantenimiento de filas reconstruidas
Las filas reconstruidas se reconocen por `cedula LIKE 'RECUPERADO-%'` y nombre `Solicitud #N (recuperada)`.
A medida que el equipo identifique los datos reales, pueden actualizarse con `UPDATE` normal.
