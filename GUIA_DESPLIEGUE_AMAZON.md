# 🚀 Guía de Despliegue - BAES en Amazon EC2

## 📋 Resumen
Esta guía te permitirá desplegar la aplicación BAES en tu servidor Amazon EC2 usando Docker, sin afectar las aplicaciones existentes.

## 🏗️ Arquitectura del Despliegue

```
Internet → Nginx (Puerto 443/80) → Docker Container (Puerto 8086) → PHP/Apache
                                     ↓
                                MySQL Container (Puerto 3312)
                                     ↓
                            phpMyAdmin Container (Puerto 8089)
```

## 📦 Contenedores Docker

| Servicio | Puerto Host | Puerto Container | Descripción |
|----------|-------------|------------------|-------------|
| `motus_php` | 8086 | 80 | Aplicación PHP/Apache |
| `motus_db` | 3312 | 3306 | Base de datos MySQL |
| `motus_phpmyadmin` | 8089 | 80 | Interfaz phpMyAdmin |

## 🔧 Pasos de Despliegue

### 1. Preparar el Servidor

```bash
# Conectar al servidor
ssh -i Petro.pem ubuntu@[IP_SERVIDOR]

# Navegar al directorio de trabajo
cd /home/ubuntu/motus

# Crear directorio para la nueva aplicación
mkdir baes
cd baes
```

### 2. Subir los Archivos

```bash
# Desde tu máquina local, subir los archivos
scp -i Petro.pem -r . ubuntu@[IP_SERVIDOR]:/home/ubuntu/motus/baes/

# O usar rsync para sincronización
rsync -avz -e "ssh -i Petro.pem" --exclude '.git' --exclude 'node_modules' . ubuntu@[IP_SERVIDOR]:/home/ubuntu/motus/baes/
```

### 3. Configurar Variables de Entorno

```bash
# En el servidor, crear archivo .env
cd /home/ubuntu/motus/baes
cp env.example .env

# Editar el archivo .env con los valores correctos
nano .env
```

### 4. Configurar Nginx

```bash
# Copiar configuración de Nginx
sudo cp nginx-motus.grupopcr.com.pa /etc/nginx/sites-available/motus.grupopcr.com.pa

# Crear enlace simbólico
sudo ln -s /etc/nginx/sites-available/motus.grupopcr.com.pa /etc/nginx/sites-enabled/

# Verificar configuración
sudo nginx -t

# Recargar Nginx
sudo systemctl reload nginx
```

### 5. Configurar SSL con Let's Encrypt

```bash
# Instalar Certbot si no está instalado
sudo apt update
sudo apt install certbot python3-certbot-nginx

# Obtener certificado SSL
sudo certbot --nginx -d motus.grupopcr.com.pa -d www.motus.grupopcr.com.pa

# Verificar renovación automática
sudo certbot renew --dry-run
```

### 6. Construir y Ejecutar Contenedores

```bash
# Construir las imágenes
docker-compose build

# Ejecutar los contenedores en segundo plano
docker-compose up -d

# Verificar que los contenedores estén ejecutándose
docker-compose ps
```

### 7. Configurar Base de Datos

```bash
# Ejecutar migraciones (si es necesario)
docker-compose exec motus_php php ejecutar_migraciones_simple.php

# Verificar conexión a la base de datos
docker-compose exec motus_db mysql -u motus_user -p motus_baes
```

## 🔍 Verificación del Despliegue

### 1. Verificar Contenedores

```bash
# Ver todos los contenedores
docker ps

# Ver logs de la aplicación
docker-compose logs motus_php

# Ver logs de la base de datos
docker-compose logs motus_db
```

### 2. Verificar Nginx

```bash
# Verificar configuración
sudo nginx -t

# Ver logs de Nginx
sudo tail -f /var/log/nginx/motus.grupopcr.com.pa.access.log
sudo tail -f /var/log/nginx/motus.grupopcr.com.pa.error.log
```

### 3. Probar Aplicación

```bash
# Probar conexión directa al contenedor
curl http://localhost:8086

# Probar a través de Nginx (desde el navegador)
https://motus.grupopcr.com.pa
```

## 🛠️ Comandos de Mantenimiento

### Reiniciar Servicios

```bash
# Reiniciar contenedores
docker-compose restart

# Reiniciar solo PHP
docker-compose restart motus_php

# Reiniciar Nginx
sudo systemctl restart nginx
```

### Actualizar Aplicación

```bash
# Detener contenedores
docker-compose down

# Actualizar código
git pull origin main

# Reconstruir y ejecutar
docker-compose up -d --build
```

### Backup de Base de Datos

```bash
# Crear backup
docker-compose exec motus_db mysqldump -u motus_user -p motus_baes > backup_$(date +%Y%m%d_%H%M%S).sql

# Restaurar backup
docker-compose exec -T motus_db mysql -u motus_user -p motus_baes < backup_file.sql
```

## 📊 Monitoreo

### Ver Recursos

```bash
# Ver uso de recursos de contenedores
docker stats

# Ver logs en tiempo real
docker-compose logs -f motus_php
```

### Verificar Salud de la Aplicación

```bash
# Verificar respuesta HTTP
curl -I https://motus.grupopcr.com.pa

# Verificar base de datos
docker-compose exec motus_db mysql -u motus_user -p -e "SELECT COUNT(*) FROM usuarios;"
```

## 🚨 Solución de Problemas

### Problema: Contenedor no inicia
```bash
# Ver logs detallados
docker-compose logs motus_php

# Verificar configuración
docker-compose config
```

### Problema: Error 502 Bad Gateway
```bash
# Verificar que el contenedor esté ejecutándose
docker ps | grep motus_php

# Verificar puerto
netstat -tlnp | grep 8086
```

### Problema: Error de Base de Datos
```bash
# Verificar conexión
docker-compose exec motus_db mysql -u root -p

# Ver logs de MySQL
docker-compose logs motus_db
```

## 📝 Notas Importantes

1. **Puertos únicos**: Cada contenedor usa puertos diferentes para evitar conflictos
2. **Volúmenes persistentes**: Los datos se mantienen entre reinicios
3. **SSL automático**: Let's Encrypt renueva certificados automáticamente
4. **Logs centralizados**: Todos los logs están en `/var/log/nginx/`
5. **Backup recomendado**: Hacer backup regular de la base de datos

## 🔐 Seguridad

- ✅ SSL/TLS habilitado
- ✅ Headers de seguridad configurados
- ✅ Límites de tamaño de archivo
- ✅ Base de datos aislada en contenedor
- ✅ phpMyAdmin solo accesible desde IP específica (opcional)

## 📞 Acceso a Servicios

- **Aplicación Principal**: https://motus.grupopcr.com.pa
- **phpMyAdmin**: http://[IP_SERVIDOR]:8089 (solo para desarrollo)
- **Logs**: `/var/log/nginx/motus.grupopcr.com.pa.*.log`

¡Tu aplicación BAES estará lista para producción! 🎉
