# 📦 Resumen de Archivos para Despliegue en Amazon EC2

## 🎯 Objetivo
Desplegar la aplicación BAES en el servidor Amazon EC2 con dominio `motus.grupopcr.com.pa` usando Docker, sin afectar las aplicaciones existentes.

## 📁 Archivos Creados

### 🐳 Docker
- **`Dockerfile`** - Imagen personalizada de PHP 8.1 con Apache
- **`docker-compose.yml`** - Orquestación de contenedores (PHP, MySQL, phpMyAdmin)
- **`env.example`** - Plantilla de variables de entorno

### 🌐 Nginx
- **`nginx-motus.grupopcr.com.pa`** - Configuración del sitio web con SSL

### 🚀 Scripts de Instalación
- **`install_baes.sh`** - Script automatizado de instalación
- **`GUIA_DESPLIEGUE_AMAZON.md`** - Guía completa paso a paso
- **`COMANDOS_DESPLIEGUE.md`** - Comandos rápidos de referencia

### 🔒 Seguridad
- **`.gitignore`** - Actualizado para excluir archivos sensibles

## 🏗️ Arquitectura de Contenedores

```
┌─────────────────────────────────────────────────────────────┐
│                    Amazon EC2 Server                       │
├─────────────────────────────────────────────────────────────┤
│  Nginx (Puerto 80/443)                                     │
│  └── motus.grupopcr.com.pa                                 │
│      └── Proxy → Docker Container (Puerto 8086)            │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │                Docker Network                           ││
│  │                                                         ││
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     ││
│  │  │ motus_php   │  │ motus_db    │  │motus_phpmy  │     ││
│  │  │ :8086       │  │ :3312       │  │ :8089       │     ││
│  │  │ PHP/Apache  │  │ MySQL 8.0   │  │ phpMyAdmin  │     ││
│  │  └─────────────┘  └─────────────┘  └─────────────┘     ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

## 📋 Pasos de Despliegue (Resumen)

### 1. Preparación Local
```bash
# Verificar archivos
ls -la Dockerfile docker-compose.yml nginx-motus.grupopcr.com.pa install_baes.sh

# Subir al servidor
rsync -avz -e "ssh -i Petro.pem" --exclude '.git' . ubuntu@[IP_SERVIDOR]:/home/ubuntu/motus/baes/
```

### 2. En el Servidor
```bash
# Conectar
ssh -i Petro.pem ubuntu@[IP_SERVIDOR]

# Navegar
cd /home/ubuntu/motus/baes

# Instalar automáticamente
sudo ./install_baes.sh

# Configurar SSL
sudo certbot --nginx -d motus.grupopcr.com.pa -d www.motus.grupopcr.com.pa
```

### 3. Verificación
```bash
# Ver contenedores
docker ps | grep motus

# Probar aplicación
curl https://motus.grupopcr.com.pa
```

## 🔧 Configuración de Puertos

| Servicio | Puerto Host | Puerto Container | Descripción |
|----------|-------------|------------------|-------------|
| `motus_php` | 8086 | 80 | Aplicación PHP/Apache |
| `motus_db` | 3312 | 3306 | Base de datos MySQL |
| `motus_phpmyadmin` | 8089 | 80 | Interfaz phpMyAdmin |
| Nginx | 80/443 | - | Proxy reverso |

## 🛡️ Características de Seguridad

- ✅ **SSL/TLS** con Let's Encrypt
- ✅ **Headers de seguridad** configurados
- ✅ **Límites de archivo** (50MB)
- ✅ **Base de datos aislada** en contenedor
- ✅ **Firewall configurado** (UFW)
- ✅ **Archivos sensibles** excluidos de Git

## 📊 Monitoreo y Logs

- **Logs de aplicación**: `docker-compose logs -f motus_php`
- **Logs de Nginx**: `/var/log/nginx/motus.grupopcr.com.pa.*.log`
- **Logs de base de datos**: `docker-compose logs -f motus_db`
- **Monitoreo de recursos**: `docker stats`

## 🔄 Comandos de Mantenimiento

```bash
# Reiniciar servicios
docker-compose restart

# Actualizar aplicación
docker-compose down && docker-compose up -d --build

# Backup de base de datos
docker-compose exec motus_db mysqldump -u motus_user -p motus_baes > backup.sql

# Ver estado general
docker-compose ps && sudo systemctl status nginx
```

## 🚨 Solución de Problemas Comunes

1. **Error 502**: Verificar que el contenedor PHP esté ejecutándose
2. **Error de SSL**: Renovar certificados con `sudo certbot renew`
3. **Error de base de datos**: Verificar conexión y logs
4. **Puerto ocupado**: Verificar que no haya conflictos con otros servicios

## 📞 Acceso a Servicios

- **Aplicación Principal**: https://motus.grupopcr.com.pa
- **phpMyAdmin**: http://[IP_SERVIDOR]:8089
- **Logs**: `/var/log/nginx/motus.grupopcr.com.pa.*.log`

## ✅ Checklist de Despliegue

- [ ] Archivos subidos al servidor
- [ ] Script de instalación ejecutado
- [ ] Contenedores construidos y ejecutándose
- [ ] Nginx configurado y funcionando
- [ ] SSL configurado con Let's Encrypt
- [ ] Base de datos migrada (si es necesario)
- [ ] Aplicación accesible vía HTTPS
- [ ] phpMyAdmin accesible (opcional)
- [ ] Logs funcionando correctamente
- [ ] Backup configurado

¡Tu aplicación BAES estará lista para producción! 🚀

## 📝 Notas Finales

- **No afecta aplicaciones existentes**: Usa puertos únicos
- **Escalable**: Fácil agregar más contenedores
- **Mantenible**: Scripts automatizados para updates
- **Seguro**: SSL, firewall y headers de seguridad
- **Monitoreado**: Logs centralizados y comandos de verificación
