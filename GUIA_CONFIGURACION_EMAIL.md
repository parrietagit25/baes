# Guía de Configuración del Sistema de Correos

Este documento explica cómo configurar el sistema de envío de correos electrónicos en BAES.

## Requisitos

- PHP 7.4 o superior
- Composer instalado
- Servidor SMTP configurado (Gmail, SendGrid, Mailgun, etc.)

## Instalación

### 1. Instalar dependencias

```bash
composer install
```

Esto instalará PHPMailer, la librería utilizada para el envío de correos.

### 2. Configurar variables de entorno

Copia el archivo `env.example` a `.env` y configura las variables SMTP:

```bash
cp env.example .env
```

Edita el archivo `.env` con tus credenciales SMTP:

```env
# Configuración SMTP
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=tu_email@gmail.com
SMTP_PASSWORD=tu_contraseña_app
SMTP_FROM_EMAIL=noreply@baes.com
SMTP_FROM_NAME=Sistema BAES
SMTP_REPLY_TO=soporte@baes.com
SMTP_REPLY_TO_NAME=Soporte BAES
SMTP_DEBUG=false

# URL de la aplicación
APP_URL=http://localhost:8086
```

### 3. Configurar PHP para leer variables de entorno

Si no tienes un archivo `.env` cargado automáticamente, puedes modificar directamente `config/email.php`:

```php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'tu_email@gmail.com',
    'smtp_password' => 'tu_contraseña_app',
    // ... resto de configuración
];
```

## Configuración por Proveedor SMTP

### Gmail

1. **Habilitar contraseña de aplicación:**
   - Ve a tu cuenta de Google
   - Seguridad → Verificación en 2 pasos (debe estar activada)
   - Contraseñas de aplicaciones → Generar nueva contraseña
   - Copia la contraseña generada

2. **Configuración:**
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_SECURE=tls
   SMTP_USERNAME=tu_email@gmail.com
   SMTP_PASSWORD=contraseña_de_aplicación_generada
   ```

### SendGrid

```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=apikey
SMTP_PASSWORD=tu_api_key_de_sendgrid
```

### Mailgun

```env
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=postmaster@tu-dominio.mailgun.org
SMTP_PASSWORD=tu_contraseña_mailgun
```

### Otros proveedores

Consulta la documentación de tu proveedor SMTP para obtener los valores correctos de host, puerto y seguridad.

## Uso del Sistema

### Envío automático

El sistema envía correos automáticamente en los siguientes escenarios:

1. **Cuando el banco responde a una solicitud:**
   - Se notifica al vendedor asociado
   - Si está aprobada, se notifica al cliente

2. **Cuando se solicita una reevaluación:**
   - Se notifica al banco que debe reevaluar

3. **Cuando se asigna una nueva solicitud a un banco:**
   - Se notifica al banco (implementar en `api/usuarios_banco_solicitudes.php`)

### Uso manual desde código

```php
require_once 'includes/email_helper.php';

// Notificar al vendedor
$resultado = enviarNotificacionVendedor($solicitudId);

// Enviar recordatorio al banco
$resultado = enviarRecordatorioBanco($solicitudId, $usuarioBancoId);

// Notificar al cliente de aprobación
$resultado = notificarClienteAprobacion($solicitudId);

// Notificar cambio de estado
$resultado = notificarGestorCambioEstado($solicitudId, $estadoAnterior, $estadoNuevo);
```

### Uso directo de EmailService

```php
require_once 'includes/EmailService.php';

$emailService = new EmailService();

$resultado = $emailService->enviarCorreo(
    'destinatario@email.com',
    'Nombre Destinatario',
    'Asunto del correo',
    '<h1>Contenido HTML</h1><p>Mensaje del correo</p>',
    'Contenido en texto plano'
);

if ($resultado['success']) {
    echo "Correo enviado correctamente";
} else {
    echo "Error: " . $resultado['message'];
}
```

## Templates de Correo

Los templates HTML se encuentran en `templates/email/`:

- `notificacion_banco_responde.php` - Cuando el banco responde
- `recordatorio_banco.php` - Recordatorio al banco
- `notificacion_nueva_solicitud.php` - Nueva solicitud asignada
- `notificacion_cliente_aprobacion.php` - Cliente aprobado
- `notificacion_cambio_estado.php` - Cambio de estado
- `notificacion_reevaluacion.php` - Solicitud de reevaluación
- `base.php` - Template base (usado por todos)

### Crear un nuevo template

1. Crea un archivo en `templates/email/mi_template.php`
2. Define la variable `$content` con el HTML del correo
3. Incluye el template base al final:

```php
<?php
$content = '
    <h2>Mi Notificación</h2>
    <p>Contenido del correo...</p>
';

include __DIR__ . '/base.php';
?>
```

4. Usa el template desde EmailService:

```php
$emailService->enviarTemplate(
    'destinatario@email.com',
    'Nombre',
    'mi_template',
    ['datos' => 'valores']
);
```

## Solución de Problemas

### Error: "SMTP connect() failed"

- Verifica que las credenciales SMTP sean correctas
- Asegúrate de que el puerto no esté bloqueado por firewall
- Para Gmail, verifica que uses una contraseña de aplicación, no tu contraseña normal

### Error: "Authentication failed"

- Verifica usuario y contraseña
- Para Gmail, asegúrate de usar contraseña de aplicación
- Verifica que "Permitir aplicaciones menos seguras" esté desactivado (Gmail ya no lo permite)

### Los correos no se envían pero no hay error

- Activa el modo debug: `SMTP_DEBUG=true` en la configuración
- Revisa los logs de PHP: `error_log` en PHP
- Verifica que el servidor tenga acceso a internet para conectarse al SMTP

### Correos van a spam

- Configura SPF, DKIM y DMARC en tu dominio
- Usa un servicio profesional como SendGrid o Mailgun
- Evita usar Gmail para envío masivo

## Pruebas

Para probar el sistema de correos, puedes crear un script de prueba:

```php
<?php
require_once 'config/database.php';
require_once 'includes/email_helper.php';

// Probar envío de correo
$emailService = new EmailService();
$resultado = $emailService->enviarCorreo(
    'tu_email@ejemplo.com',
    'Tu Nombre',
    'Prueba de Correo',
    '<h1>Prueba</h1><p>Este es un correo de prueba del sistema BAES.</p>'
);

var_dump($resultado);
?>
```

## Seguridad

- **Nunca** subas el archivo `.env` al repositorio
- Usa contraseñas de aplicación, no contraseñas principales
- Mantén las credenciales SMTP seguras
- Considera usar variables de entorno del servidor en producción

## Soporte

Para más información sobre PHPMailer, consulta: https://github.com/PHPMailer/PHPMailer

