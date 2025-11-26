# Instalación de SendGrid

## Configuración

✅ **Remitente:** noreply@automarketrentacar.com
✅ **Nombre:** Automarket Rent a Car

**IMPORTANTE:** La API Key debe configurarse en el archivo `.env` o como variable de entorno del servidor.

## Instalación

### 1. Instalar dependencias con Composer

```bash
cd /home/ubuntu/motus/baes
composer install
```

Esto instalará el SDK oficial de SendGrid (`sendgrid/sendgrid`).

### 2. Verificar instalación

```bash
# Verificar que SendGrid está instalado
ls -la vendor/sendgrid/

# Probar que el autoload funciona
php -r "require 'vendor/autoload.php'; echo 'SendGrid cargado correctamente';"
```

### 3. Probar el sistema

```bash
php test_email.php
```

## Ventajas de SendGrid sobre SMTP

- ✅ **Más confiable:** API REST en lugar de SMTP
- ✅ **Mejor deliverability:** Menos probabilidad de ir a spam
- ✅ **Analytics:** Estadísticas de envío y apertura
- ✅ **Más rápido:** No requiere conexión SMTP
- ✅ **Escalable:** Maneja grandes volúmenes mejor

## Configuración en SendGrid

1. **Verificar dominio:** Asegúrate de que `automarketrentacar.com` esté verificado en SendGrid
2. **Configurar remitente:** El remitente `noreply@automarketrentacar.com` debe estar autorizado
3. **Límites:** Verifica los límites de tu plan de SendGrid

## Solución de Problemas

### Error: "API Key inválida"
- Verifica que la API Key esté correcta en `config/email.local.php` o como variable de entorno
- Asegúrate de que la API Key tenga permisos de envío
- Usa el script `configurar_sendgrid_servidor.sh` para configurar en el servidor

### Error: "From email not verified"
- Verifica el dominio en SendGrid
- O usa un remitente verificado en tu cuenta de SendGrid

### Los correos no se envían
- Revisa los logs: `tail -f logs/error.log`
- Verifica el estado de la respuesta en los logs

## Verificación de Envío

Los correos enviados aparecerán en el dashboard de SendGrid:
- https://app.sendgrid.com/activity

Puedes ver:
- Estado de entrega
- Aperturas
- Clics
- Rebotados

