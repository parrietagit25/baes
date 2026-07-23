# Contexto de continuidad — MOTUS / BAES

Documento de handoff para retomar trabajo en otra ventana de Cursor.  
Última actualización: **2026-07-20** (rama `main`, commit tipico reciente `ba7f761`).

---

## 1. Proyecto y entornos

| Ítem | Valor |
|------|--------|
| App | MOTUS — sistema de solicitudes de crédito / financiamiento |
| Repo local | `c:\xampp\htdocs\baes` |
| Prod URL | https://motus.automarket.com.pa |
| Repo git | `https://github.com/parrietagit25/baes.git` (rama `main`) |
| SSH alias prod | `motus` → servidor DigitalOcean |
| Path en servidor | `/root/baes` |
| Contenedores | `motus_php` (Apache/PHP), `motus_db` (MySQL 8) |
| BD local (XAMPP) | `solicitud_credito` / root sin pass |
| BD prod (Docker) | `motus_baes` / user `motus_user` |

### Despliegue habitual

```bash
git add … && git commit -m "…"
git push -u origin HEAD
ssh motus "cd /root/baes && git pull"
```

- Responder siempre en **español**.
- No hacer commit/push/deploy salvo que el usuario lo pida (en esta línea de trabajo suele pedir deploy).
- En PowerShell, `$()` y `*` dentro de `ssh "…-e \"SELECT…\""` se rompen: preferir archivo `.sql` + `scp` + `docker exec -i … < file.sql`.

### Credenciales MySQL prod (contenedor)

```text
MYSQL_USER=motus_user
MYSQL_PASSWORD=motus_2026_automarket
MYSQL_ROOT_PASSWORD=automarket_motus_2026
MYSQL_DATABASE=motus_baes
```

Consulta típica:

```bash
scp archivo.sql motus:/tmp/q.sql
ssh motus "docker exec -i motus_db mysql -umotus_user -pmotus_2026_automarket motus_baes < /tmp/q.sql"
```

---

## 2. Arquitectura relevante

### Roles

- `ROLE_ADMIN` — ve todo; páginas admin en sidebar.
- `ROLE_GESTOR` — solo sus SC (`gestor_id = session`).
- `ROLE_BANCO` — solo SC asignadas en `usuarios_banco_solicitudes` (activo).
- `ROLE_ADMIN_BANCO` — ve todas las SC asignadas a usuarios de su misma entidad (`usuarios.banco_id`). Requiere `banco_id` en el usuario. No decide por otros; el martillo sigue siendo de `ROLE_BANCO` con asignación propia. Helper: `includes/banco_scope_helper.php`.
- `ROLE_VENDEDOR` — acceso a solicitudes; en lista activa no filtra por vendedor (ve activas).

Acceso por página: `includes/validar_acceso.php`.

### Tablas clave de flujo bancario

| Tabla | Rol |
|-------|-----|
| `solicitudes_credito` | SC; `estado` es **VARCHAR(64)** (no ENUM estricto en prod) |
| `usuarios_banco_solicitudes` | Asignación banco↔SC; `estado` activo/inactivo |
| `evaluaciones_banco` | Respuestas/propuestas; `usuario_banco_id` = **id de UBS**, no de `usuarios` |
| `notas_solicitud` | Muro / historial narrativo |
| `configuracion_sistema` | Chatbot, mantenimiento |

FK importante: borrar una fila de `usuarios_banco_solicitudes` hace **CASCADE** y borra sus `evaluaciones_banco`.

### Campo Pipe

- Columna: `solicitudes_credito.email_pipedrive`
- UI: campo “Email PipeDrive” en modal de solicitud.

---

## 3. Estados de solicitud

### Histórico vs activas

- **Histórico:** `Completada`, `Rechazada`, `Desistimiento`
- **Activas:** todo lo demás (incluye estados custom)

### Estados en uso

| Estado | Notas |
|--------|--------|
| Nueva | Alta |
| En Revisión Banco | Al **asignar** usuario banco (`api/usuarios_banco_solicitudes.php`) |
| Reevaluación por los Bancos | Nuevo (jul 2026): modal Cambiar Estado; mismo flujo operativo que revisión banco |
| Evaluacion / Comité / Reconsideración | Manuales vía Cambiar Estado |
| Pre Aprobado / Aprobado con Condición | Manuales |
| Aprobada | Manual / legacy |
| Rechazada / Completada / Desistimiento | Van a histórico |

### Efecto de «Reevaluación por los Bancos»

Al cambiar a ese estado (`api/solicitudes.php` → `cambiarEstadoSolicitud`):

1. `estado = 'Reevaluación por los Bancos'`
2. Limpia `evaluacion_seleccionada`, `fecha_aprobacion_propuesta`, `comentario_seleccion_propuesta`, `evaluacion_en_reevaluacion`
3. Queda en lista **activa**
4. Bancos asignados pueden volver a usar el botón de decisión (igual que En Revisión Banco / Nueva)

Archivos: `solicitudes.php`, `api/solicitudes.php`, `js/solicitudes.js`, `api/feria_panel.php`, `js/feria_panel.js`.

### Quién ve qué (lista SC)

| Rol | Filtro |
|-----|--------|
| Admin | Todas |
| Gestor | `gestor_id = yo` |
| Banco | Asignación UBS activa |
| Vendedor | Sin filtro extra de dueño en lista (ve activas/histórico según página) |

**Banco puede decidir** (martillo) solo si estado ∈:

- `Nueva`
- `En Revisión Banco`
- `Reevaluación por los Bancos`

---

## 4. Decisiones de banco y selección de propuesta

### Guardar decisión

- API: `POST api/evaluaciones_banco.php` → `guardarEvaluacion()`
- Inserta en `evaluaciones_banco` (historial append-only)
- Actualiza resumen en SC (`respuesta_banco`, letra, plazo, etc.)

### Selección por gestor/admin

- Setea `evaluacion_seleccionada` en SC
- UI: otros bancos pierden botones **al recargar**

### Bloqueo de carrera (desplegado jul 2026)

Escenario: banco A tiene modal abierta; gestor selecciona propuesta de banco B; banco A envía igual.

**Antes:** se insertaba y sobrescribía resumen / podía mandar mails.  
**Ahora:**

- Backend rechaza si `evaluacion_seleccionada` pertenece a **otro** usuario banco
- Código respuesta: `PROPUESTA_YA_SELECCIONADA`
- Mensaje claro: no se procesa; cierra modal y recarga
- Frontend hace GET previo + el POST es la garantía

Archivos: `api/evaluaciones_banco.php`, `solicitudes.php` (`procesarAprobacion`).

Nota: en GET, `usuario_banco_id_seleccionado` debe ser el **usuario real** (vía join UBS), no el id de la fila UBS.

### Razones de decisión

Dropdowns distintos según decisión:

- Preaprobado / Aprobado condicional → lista “condicionales”
- Rechazado → lista de rechazo
- Aprobado → sin razón

### Campo Cuantía

Visible cuando hay promoción en la evaluación; migraciones en `database/migracion_evaluaciones_*.sql`.

---

## 5. Correo de Pipe (`email_pipedrive`)

### Regla general

Si la SC tiene `email_pipedrive` válido y el envío usa `EmailService::paraSolicitud($id)`, Pipe va en **CC** (también suele ir `fyi@automarketpan.com`).

Archivos: `includes/EmailService.php`, `includes/email_helper.php`.

### Cuándo Pipe recibe copia (CC)

| Evento | Destinatario principal |
|--------|-------------------------|
| Banco guarda decisión | Vendedor (`enviarNotificacionVendedor`) |
| Decisión Aprobado/Preaprobado | Cliente (`notificarClienteAprobacion`) |
| Gestor pide reevaluación de una evaluación | Ese usuario banco (`notificarReevaluacion`) |
| Botón “Enviar resumen” a un banco | Ese banco |
| “Enviar resumen a todos” | Bancos en BCC; Pipe en CC |
| Formulario público financiamiento | Vendedor/cliente al crear SC |

### Envío “directo” a Pipe

- API: `api/enviar_resumen_pipedrive.php` → `enviarResumenSolicitudPipedriveDirecto()`
- Manda a FYI y Pipe en CC
- **No hay botón en la UI** cableado hoy (API lista, UI no)

### Lo que NO dispara Pipe solo

- Guardar/editar SC
- Solo asignar bancos (no hay mail auto al asignar en el flujo actual)
- Solo “Cambiar Estado” (helper de mail a gestor existe pero no está integrado en ese POST)

---

## 6. Configuración / export BD

- Página: `configuracion.php` (solo admin en menú)
- Export SQL: `api/exportar_base_datos.php`
- **Solo `user_id = 1`** ve el botón y puede descargar `.sql` estilo phpMyAdmin (estructura + datos + vistas)
- Otros admins: 403

---

## 7. Servidor Motus (chequeo jul 2026 — no modificar)

| Recurso | Estado |
|---------|--------|
| Disco | **33 GB** total; ~13 usados / ~21 libres (~37%) |
| RAM | **~1 GB**; crítica (~37–40 MB disponibles); MySQL ~500 MB |
| Swap | **0** |
| CPU | 1 vCPU; load bajo en el chequeo |
| Datos MySQL | ~465 MB en `/var/lib/mysql` |

Recomendación fase 2 (más bancos/tráfico): subir a **≥2 GB RAM** (ideal 4 GB + 2 vCPU). Disco no urgente; mantener ≥8–10 GB libres.

---

## 8. Caso investigado: SC 1340

- Cliente: Dayana Prueba
- En BD Motus: **0** filas en `evaluaciones_banco` para esa SC
- Solo 2 asignaciones (Banco Pedro + BAC)
- No era bug de UI: nunca hubo respuestas guardadas (posible confusión asignaciones vs evaluaciones)

---

## 9. Otros comportamientos útiles

- Sidebar: acordeones Solicitudes / Administración / Feria / Reportes (`includes/sidebar.php`)
- Listado SC: fila rojo claro si enviada a banco ≥2 h sin ninguna evaluación
- Reservas Excel: mapeo por **cabeceras**, no columnas fijas
- Correo aprobación cliente: incluye banco, vehículo, precio, abono+bono, plazo, cuota, promo, siguientes pasos
- Chatbot / mantenimiento: toggles en Configuración (`api/configuracion_sistema.php`)

---

## 10. Archivos “tocar primero” por tema

| Tema | Archivos |
|------|----------|
| Lista / modales SC | `solicitudes.php`, `js/solicitudes.js` |
| Evaluaciones banco | `api/evaluaciones_banco.php` |
| Asignar bancos | `api/usuarios_banco_solicitudes.php` |
| Cambiar estado | `api/solicitudes.php` (`cambiarEstadoSolicitud`) |
| Emails | `includes/EmailService.php`, `includes/email_helper.php`, `templates/email/` |
| Pipe resumen | `api/enviar_resumen_pipedrive.php`, `api/enviar_resumen_banco*.php` |
| Config / dump | `configuracion.php`, `api/exportar_base_datos.php` |
| BD conn | `config/database.php` |

---

## 11. Commits recientes relevantes

```text
ba7f761 Add bank reevaluation status with same workflow as bank review.
5a1bc4a Reject late bank decisions when another proposal is already selected.
08d960e Allow admin id 1 to export full database as SQL from Configuracion.
0de5340 Expand client approval email with bank and financing details.
bfa6b00 Use decision-specific reason lists for bank evaluations.
```

---

## 12. Pendientes / ideas no hechas

- Botón UI para “Enviar resumen a Pipe” (API ya existe)
- Integrar `notificarBancoNuevaSolicitud` al asignar banco (doc antigua lo menciona; código helper existe)
- Integrar `notificarGestorCambioEstado` en el POST de cambiar estado
- Upgrade de droplet (RAM) para fase 2
- Posible endurecer más: bloquear abrir modal de decisión si ya hay selección ajena (hoy se bloquea al enviar)

---

## 13. Cómo seguir en la nueva ventana

1. Leer este archivo.
2. Confirmar `git status` / `git log -5` (debe estar al día con `origin/main` si no hubo trabajo local).
3. Preguntar al usuario la tarea concreta; no redeployar “por si acaso”.
4. Si hay que inspeccionar prod: usar SQL vía archivo + `docker exec -i`, no inline con PowerShell.
