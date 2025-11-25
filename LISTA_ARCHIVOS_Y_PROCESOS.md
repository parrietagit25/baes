# Lista de Archivos y Procesos - Sistema de Correos

## 📁 ARCHIVOS CREADOS (16 archivos)

### Sistema Core
1. `includes/EmailService.php`
2. `includes/email_helper.php`
3. `config/email.php`

### Templates de Correo (7 archivos)
4. `templates/email/base.php`
5. `templates/email/notificacion_banco_responde.php`
6. `templates/email/recordatorio_banco.php`
7. `templates/email/notificacion_nueva_solicitud.php`
8. `templates/email/notificacion_cliente_aprobacion.php`
9. `templates/email/notificacion_cambio_estado.php`
10. `templates/email/notificacion_reevaluacion.php`

### Configuración
11. `composer.json`
12. `env.example`
13. `test_email.php`

### Documentación
14. `GUIA_CONFIGURACION_EMAIL.md`
15. `INSTALACION_EMAIL.md`
16. `DOCUMENTACION_EMAILS.md`
17. `RESUMEN_SISTEMA_EMAILS.md`
18. `LISTA_ARCHIVOS_Y_PROCESOS.md` (este archivo)

---

## 📝 ARCHIVOS MODIFICADOS (4 archivos)

1. `api/evaluaciones_banco.php` - Agregado envío de correos
2. `api/usuarios_banco_solicitudes.php` - Agregado envío de correos
3. `Dockerfile` - Agregado instalación de Composer
4. `.gitignore` - Agregado vendor/

---

## 📧 PROCESOS DE ENVÍO DE CORREOS

### Proceso 1: Banco Responde a Solicitud
**Archivo:** `api/evaluaciones_banco.php`
**Función:** `guardarEvaluacion()`
**Cuándo:** Usuario banco guarda evaluación con decisión (Aprobado/Pre Aprobado/Rechazado)

**Destinatarios:**
- **Vendedor** (email del vendedor asociado a la solicitud)
- **Cliente** (email del cliente) - Solo si está Aprobado/Pre Aprobado

---

### Proceso 2: Asignar Usuario Banco
**Archivo:** `api/usuarios_banco_solicitudes.php`
**Función:** `asignarUsuarioBanco()`
**Cuándo:** Admin/Gestor asigna un usuario banco a una solicitud

**Destinatario:**
- **Usuario Banco** (email del usuario banco asignado)

---

### Proceso 3: Solicitar Reevaluación
**Archivo:** `api/evaluaciones_banco.php`
**Función:** `solicitarReevaluacion()`
**Cuándo:** Admin/Gestor solicita reevaluación de una propuesta bancaria

**Destinatario:**
- **Usuario Banco** (email del banco que hizo la evaluación original)

---

## ✅ CONFIGURACIÓN APLICADA

**Servidor:** smtp-mail.outlook.com:587
**Usuario:** notificaciones@grupopcr.com.pa
**Remitente:** notificaciones@grupopcr.com.pa
**Estado:** ✅ Configurado y listo

