# Documentación del Sistema de Correos - BAES

## 📋 Lista de Archivos Creados

### Archivos Principales
1. **`includes/EmailService.php`** - Clase principal para envío de correos con PHPMailer
2. **`includes/email_helper.php`** - Funciones helper para escenarios específicos
3. **`config/email.php`** - Configuración SMTP (Outlook configurado)

### Templates de Correo
4. **`templates/email/base.php`** - Template base HTML para todos los correos
5. **`templates/email/notificacion_banco_responde.php`** - Template cuando el banco responde
6. **`templates/email/recordatorio_banco.php`** - Template de recordatorio al banco
7. **`templates/email/notificacion_nueva_solicitud.php`** - Template de nueva solicitud asignada
8. **`templates/email/notificacion_cliente_aprobacion.php`** - Template de aprobación al cliente
9. **`templates/email/notificacion_cambio_estado.php`** - Template de cambio de estado
10. **`templates/email/notificacion_reevaluacion.php`** - Template de solicitud de reevaluación

### Configuración y Documentación
11. **`composer.json`** - Dependencias del proyecto (PHPMailer)
12. **`env.example`** - Ejemplo de variables de entorno
13. **`GUIA_CONFIGURACION_EMAIL.md`** - Guía completa de configuración
14. **`INSTALACION_EMAIL.md`** - Guía rápida de instalación
15. **`test_email.php`** - Script de prueba del sistema de correos
16. **`DOCUMENTACION_EMAILS.md`** - Este documento

## 📝 Lista de Archivos Modificados

1. **`api/evaluaciones_banco.php`**
   - Agregado `require_once '../includes/email_helper.php'`
   - Integrado envío de correos en `guardarEvaluacion()` - Notifica al vendedor y cliente
   - Integrado envío de correos en `solicitarReevaluacion()` - Notifica al banco

2. **`api/usuarios_banco_solicitudes.php`**
   - Agregado `require_once '../includes/email_helper.php'`
   - Integrado envío de correos en `asignarUsuarioBanco()` - Notifica al banco asignado

3. **`Dockerfile`**
   - Agregado comando para instalar dependencias de Composer

4. **`.gitignore`**
   - Agregado `vendor/` y `composer.lock` para ignorar dependencias

## 📧 Procesos de Envío de Correos

### 1. Cuando el Banco Responde a una Solicitud
**Archivo:** `api/evaluaciones_banco.php` → Función `guardarEvaluacion()`

**Cuándo se envía:**
- Cuando un usuario banco guarda una evaluación con decisión: Aprobado, Pre Aprobado, Rechazado, o Aprobado Condicional

**A quién se envía:**
- **Vendedor asociado** (si existe `vendedor_id` en la solicitud)
  - Email: `usuarios.email` donde `usuarios.id = solicitudes_credito.vendedor_id`
  - Template: `notificacion_banco_responde.php`
  - Contenido: Respuesta del banco, términos ofrecidos (letra, plazo, abono), link a la solicitud

- **Cliente** (si la respuesta es "Aprobado" o "Pre Aprobado" y existe email del cliente)
  - Email: `solicitudes_credito.email`
  - Template: `notificacion_cliente_aprobacion.php`
  - Contenido: Felicitaciones, términos aprobados, próximos pasos

**Código:**
```php
// Líneas 200-220 en api/evaluaciones_banco.php
if (in_array($respuestaBancoEnum, ['Aprobado', 'Pre Aprobado', 'Rechazado', 'Aprobado Condicional'])) {
    enviarNotificacionVendedor($solicitudId);
    if (in_array($respuestaBancoEnum, ['Aprobado', 'Pre Aprobado'])) {
        notificarClienteAprobacion($solicitudId);
    }
}
```

---

### 2. Cuando se Asigna un Usuario Banco a una Solicitud
**Archivo:** `api/usuarios_banco_solicitudes.php` → Función `asignarUsuarioBanco()`

**Cuándo se envía:**
- Cuando un administrador o gestor asigna un usuario banco a una solicitud
- El estado de la solicitud cambia automáticamente a "En Revisión Banco"

**A quién se envía:**
- **Usuario Banco asignado**
  - Email: `usuarios.email` donde `usuarios.id = usuarios_banco_solicitudes.usuario_banco_id`
  - Template: `notificacion_nueva_solicitud.php`
  - Contenido: Datos de la solicitud, información del cliente, vehículo, link para evaluar

**Código:**
```php
// Líneas 193-200 en api/usuarios_banco_solicitudes.php
$resultadoEmail = notificarBancoNuevaSolicitud($solicitud_id, $usuario_banco_id);
```

---

### 3. Cuando se Solicita una Reevaluación
**Archivo:** `api/evaluaciones_banco.php` → Función `solicitarReevaluacion()`

**Cuándo se envía:**
- Cuando un administrador o gestor solicita una reevaluación de una propuesta bancaria

**A quién se envía:**
- **Usuario Banco que hizo la evaluación original**
  - Email: `usuarios.email` del banco que creó la evaluación
  - Template: `notificacion_reevaluacion.php`
  - Contenido: Solicitud de reevaluación, comentario del gestor, link a la solicitud

**Código:**
```php
// Líneas 318-322 en api/evaluaciones_banco.php
$resultadoEmail = notificarReevaluacion($solicitudId, $evaluacionId, $comentario);
```

---

## 🔧 Funciones Helper Disponibles (No Integradas Automáticamente)

Estas funciones están disponibles pero requieren ser llamadas manualmente desde el código:

### 4. Enviar Recordatorio al Banco
**Función:** `enviarRecordatorioBanco($solicitudId, $usuarioBancoId)`

**Uso sugerido:**
- Botón "Enviar Recordatorio" en la interfaz
- Tareas programadas (cron) para solicitudes pendientes > 24 horas

**A quién se envía:**
- Usuario banco asignado a la solicitud

**Template:** `recordatorio_banco.php`

---

### 5. Notificar Cambio de Estado
**Función:** `notificarGestorCambioEstado($solicitudId, $estadoAnterior, $estadoNuevo)`

**Uso sugerido:**
- Cuando el estado de una solicitud cambia (ej: de "Nueva" a "En Revisión Banco")
- Integrar en `api/solicitudes.php` cuando se actualiza el estado

**A quién se envía:**
- Gestor de la solicitud (`solicitudes_credito.gestor_id`)

**Template:** `notificacion_cambio_estado.php`

---

## 📊 Resumen de Destinatarios por Proceso

| Proceso | Destinatario | Template | Estado |
|---------|-------------|----------|--------|
| Banco responde | Vendedor + Cliente (si aprobado) | `notificacion_banco_responde.php` + `notificacion_cliente_aprobacion.php` | ✅ Integrado |
| Asignar banco | Usuario Banco asignado | `notificacion_nueva_solicitud.php` | ✅ Integrado |
| Solicitar reevaluación | Usuario Banco evaluador | `notificacion_reevaluacion.php` | ✅ Integrado |
| Recordatorio banco | Usuario Banco asignado | `recordatorio_banco.php` | ⚠️ Disponible (no integrado) |
| Cambio de estado | Gestor | `notificacion_cambio_estado.php` | ⚠️ Disponible (no integrado) |

## 🔐 Configuración SMTP Actual

**Servidor:** Outlook (smtp-mail.outlook.com)
**Puerto:** 587
**Seguridad:** TLS
**Usuario:** notificaciones@grupopcr.com.pa
**Remitente:** notificaciones@grupopcr.com.pa

## 📝 Notas Importantes

1. **Manejo de Errores:** Si el envío de correo falla, se registra en el log de errores pero NO interrumpe el proceso principal
2. **Validación:** Los correos solo se envían si el destinatario tiene un email válido en la base de datos
3. **Templates:** Todos los templates son HTML responsivos y se adaptan a diferentes clientes de correo
4. **Logs:** Los errores de envío se registran en `error_log` de PHP para debugging

## 🚀 Próximos Pasos Sugeridos

1. Integrar `enviarRecordatorioBanco()` en un botón de la interfaz
2. Integrar `notificarGestorCambioEstado()` en `api/solicitudes.php`
3. Crear tareas programadas (cron) para recordatorios automáticos
4. Agregar notificaciones cuando se selecciona una propuesta ganadora
5. Implementar cola de correos para envíos masivos

