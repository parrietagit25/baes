# Fase 2: Reglas de mapeo y consolidación

Reglas para reconstruir filas faltantes en `motus_baes.solicitudes_credito` a partir de tablas relacionadas. Esta es la guía que sigue el script `recovery_fase3_staging.sql`.

## Principios
- Solo reconstruir IDs presentes en relacionadas pero ausentes en `solicitudes_credito` actual.
- Nunca tocar las 60 filas ya restauradas.
- Convertir cualquier fecha inválida (`0000-00-00`) en `NULL`.
- Para campos `NOT NULL` sin fuente confiable, usar valor placeholder controlado.

## Mapeo por campo

| Campo destino                  | Origen / Regla                                                                                              | Fallback              |
|--------------------------------|-------------------------------------------------------------------------------------------------------------|-----------------------|
| id                             | `solicitud_id` faltante del universo unión                                                                  | -                     |
| gestor_id (NOT NULL)           | `historial_solicitud.usuario_id` con `tipo_accion='creacion'` -> `MIN(usuario_id)` en historial -> admin 1 | 1                     |
| banco_id                       | `usuarios.banco_id` del `usuario_banco_id` con más asignaciones activas                                     | NULL                  |
| evaluacion_seleccionada        | NULL (no derivable de forma confiable)                                                                      | NULL                  |
| evaluacion_en_reevaluacion     | NULL                                                                                                        | NULL                  |
| fecha_aprobacion_propuesta     | NULL                                                                                                        | NULL                  |
| comentario_seleccion_propuesta | NULL                                                                                                        | NULL                  |
| vendedor_id                    | NULL                                                                                                        | NULL                  |
| tipo_persona (NOT NULL)        | placeholder controlado                                                                                       | 'Natural'             |
| nombre_cliente (NOT NULL)      | `Solicitud #N (recuperada)`                                                                                 | -                     |
| cedula (NOT NULL)              | `RECUPERADO-{id}`                                                                                            | -                     |
| edad / genero / telefonos      | NULL                                                                                                        | NULL                  |
| email / pipedrive              | NULL                                                                                                        | NULL                  |
| direccion / ubicación          | NULL                                                                                                        | NULL                  |
| casado / hijos                 | 0                                                                                                            | 0                     |
| perfil_financiero (NOT NULL)   | placeholder controlado                                                                                       | 'Asalariado'          |
| ingreso / tiempo_laborar       | NULL                                                                                                        | NULL                  |
| profesion / ocupacion          | NULL                                                                                                        | NULL                  |
| nombre_empresa_negocio         | NULL                                                                                                        | NULL                  |
| estabilidad_laboral / fecha_constitucion | NULL                                                                                              | NULL                  |
| continuidad_laboral            | NULL                                                                                                        | NULL                  |
| marca_auto                     | `vehiculos_solicitud.marca` con `orden=1` o el primero por id                                                | NULL                  |
| modelo_auto                    | `vehiculos_solicitud.modelo` (mismo registro)                                                                | NULL                  |
| año_auto                       | `vehiculos_solicitud.anio`                                                                                   | NULL                  |
| kilometraje                    | `vehiculos_solicitud.kilometraje`                                                                            | NULL                  |
| precio_especial                | `vehiculos_solicitud.precio`                                                                                 | NULL                  |
| abono_porcentaje               | `vehiculos_solicitud.abono_porcentaje`                                                                       | NULL                  |
| abono_monto                    | `vehiculos_solicitud.abono_monto`                                                                            | NULL                  |
| comentarios_gestor             | NULL                                                                                                         | NULL                  |
| ejecutivo_banco                | NULL                                                                                                         | NULL                  |
| respuesta_banco                | derivar de `evaluaciones_banco.decision`: aprobado→'Aprobado', preaprobado→'Pre Aprobado', aprobado_condicional→'Aprobado Condicional', rechazado→'Rechazado', en otro caso 'Pendiente' | 'Pendiente'           |
| letra / plazo / abono_banco / promocion | tomar de la evaluación más reciente si existe                                                       | NULL                  |
| comentarios_ejecutivo_banco    | NULL                                                                                                         | NULL                  |
| respuesta_cliente              | 'Pendiente'                                                                                                  | 'Pendiente'           |
| motivo_respuesta               | NULL                                                                                                         | NULL                  |
| fecha_envio_proforma..fecha_carta_promesa | NULL                                                                                              | NULL                  |
| comentarios_fi                 | NULL                                                                                                         | NULL                  |
| estado                         | derivar de último `historial_solicitud.estado_nuevo` válido en ENUM; si no, 'Nueva'                          | 'Nueva'               |
| fecha_creacion                 | `MIN(historial_solicitud.fecha_creacion)`; fallback `MIN(usuarios_banco_solicitudes.fecha_asignacion)`; fallback `NOW()` | NOW()                 |
| fecha_actualizacion            | `MAX(historial_solicitud.fecha_creacion)`; fallback `NOW()`                                                  | NOW()                 |

## Validaciones obligatorias antes del swap
- `motus_baes_recovery.solicitudes_credito_reconstruida` no debe tener IDs duplicados ni IDs ya presentes en producción.
- Todos los campos NOT NULL deben tener valor.
- Estados deben pertenecer al ENUM válido.
- Fechas deben ser `NULL` o fechas reales (no `0000-00-00`).
