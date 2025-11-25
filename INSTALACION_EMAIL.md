# Instalación Rápida del Sistema de Correos

## Pasos Rápidos

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar SMTP

Edita `config/email.php` y configura tus credenciales SMTP:

```php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'tu_email@gmail.com',
    'smtp_password' => 'tu_contraseña_app', // Para Gmail: contraseña de aplicación
    'from_email' => 'noreply@baes.com',
    'from_name' => 'Sistema BAES',
    // ... resto de configuración
];
```

### 3. Probar el sistema

```bash
php test_email.php
```

## Configuración Gmail

1. Activa la verificación en 2 pasos en tu cuenta de Google
2. Ve a: https://myaccount.google.com/apppasswords
3. Genera una contraseña de aplicación
4. Usa esa contraseña en `smtp_password`

## Uso

El sistema envía correos automáticamente cuando:
- El banco responde a una solicitud → Notifica al vendedor y cliente
- Se solicita una reevaluación → Notifica al banco

Para más detalles, consulta `GUIA_CONFIGURACION_EMAIL.md`

