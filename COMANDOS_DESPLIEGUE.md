# 🚀 Comandos Rápidos para Despliegue en Amazon EC2

## 📤 Subir Archivos al Servidor

```bash
# Opción 1: SCP (Subir archivos)
scp -i Petro.pem -r . ubuntu@[IP_SERVIDOR]:/home/ubuntu/motus/baes/

# Opción 2: Rsync (Sincronizar, más eficiente)
rsync -avz -e "ssh -i Petro.pem" \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude '*.log' \
  --exclude 'adjuntos/*' \
  . ubuntu@[IP_SERVIDOR]:/home/ubuntu/motus/baes/
```

## 🔧 En el Servidor Amazon EC2

### 1. Conectar al Servidor
```bash
ssh -i Petro.pem ubuntu@[IP_SERVIDOR]
```

### 2. Navegar al Directorio
```bash
cd /home/ubuntu/motus/baes
```

### 3. Configurar Permisos
```bash
chmod +x install_baes.sh
sudo chown -R ubuntu:ubuntu /home/ubuntu/motus/baes
```

### 4. Ejecutar Instalación Automática
```bash
sudo ./install_baes.sh
```

### 5. Configurar Variables de Entorno
```bash
# Editar archivo .env
nano .env

# Verificar configuración
cat .env
```

### 6. Configurar SSL
```bash
# Obtener certificado SSL
sudo certbot --nginx -d motus.grupopcr.com.pa -d www.motus.grupopcr.com.pa

# Verificar renovación automática
sudo certbot renew --dry-run
```

### 7. Iniciar Aplicación
```bash
# Construir y ejecutar contenedores
docker-compose up -d --build

# Verificar estado
docker-compose ps
```

## 🔍 Verificación Rápida

### Verificar Contenedores
```bash
docker ps | grep motus
```

### Verificar Puertos
```bash
netstat -tlnp | grep -E "(8086|3312|8089)"
```

### Verificar Nginx
```bash
sudo nginx -t
sudo systemctl status nginx
```

### Probar Aplicación
```bash
# Aplicación directa
curl http://localhost:8086

# A través de Nginx
curl https://motus.grupopcr.com.pa
```

## 🛠️ Comandos de Mantenimiento

### Reiniciar Servicios
```bash
# Reiniciar contenedores
docker-compose restart

# Reiniciar Nginx
sudo systemctl restart nginx

# Reiniciar todo
sudo systemctl restart nginx && docker-compose restart
```

### Ver Logs
```bash
# Logs de aplicación
docker-compose logs -f motus_php

# Logs de base de datos
docker-compose logs -f motus_db

# Logs de Nginx
sudo tail -f /var/log/nginx/motus.grupopcr.com.pa.access.log
sudo tail -f /var/log/nginx/motus.grupopcr.com.pa.error.log
```

### Backup de Base de Datos
```bash
# Crear backup
docker-compose exec motus_db mysqldump -u motus_user -p motus_baes > backup_$(date +%Y%m%d_%H%M%S).sql

# Restaurar backup
docker-compose exec -T motus_db mysql -u motus_user -p motus_baes < backup_file.sql
```

### Actualizar Aplicación
```bash
# Detener contenedores
docker-compose down

# Actualizar código (desde tu máquina local)
rsync -avz -e "ssh -i Petro.pem" \
  --exclude '.git' \
  --exclude 'node_modules' \
  . ubuntu@[IP_SERVIDOR]:/home/ubuntu/motus/baes/

# Reconstruir y ejecutar
docker-compose up -d --build
```

## 🚨 Solución de Problemas

### Contenedor no inicia
```bash
# Ver logs detallados
docker-compose logs motus_php

# Verificar configuración
docker-compose config

# Reconstruir imagen
docker-compose build --no-cache motus_php
```

### Error 502 Bad Gateway
```bash
# Verificar que el contenedor esté ejecutándose
docker ps | grep motus_php

# Verificar puerto
netstat -tlnp | grep 8086

# Reiniciar contenedor PHP
docker-compose restart motus_php
```

### Error de Base de Datos
```bash
# Verificar conexión
docker-compose exec motus_db mysql -u root -p

# Ver logs de MySQL
docker-compose logs motus_db

# Reiniciar base de datos
docker-compose restart motus_db
```

### Error de SSL
```bash
# Verificar certificados
sudo certbot certificates

# Renovar certificados
sudo certbot renew --force-renewal

# Verificar configuración de Nginx
sudo nginx -t
```

## 📊 Monitoreo

### Ver Recursos del Sistema
```bash
# Uso de recursos de contenedores
docker stats

# Uso de memoria y CPU del sistema
htop

# Espacio en disco
df -h
```

### Verificar Salud de la Aplicación
```bash
# Verificar respuesta HTTP
curl -I https://motus.grupopcr.com.pa

# Verificar base de datos
docker-compose exec motus_db mysql -u motus_user -p -e "SELECT COUNT(*) FROM usuarios;"

# Verificar archivos de log
ls -la logs/
```

## 🔐 Seguridad

### Configurar Firewall
```bash
# Habilitar UFW
sudo ufw enable

# Permitir puertos necesarios
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Ver estado
sudo ufw status
```

### Configurar phpMyAdmin (Solo para Desarrollo)
```bash
# Restringir acceso por IP
sudo nano /etc/nginx/sites-available/motus.grupopcr.com.pa

# Agregar en la sección de phpMyAdmin:
# allow [TU_IP];
# deny all;
```

## 📝 Información Importante

- **Dominio**: motus.grupopcr.com.pa
- **Puerto Aplicación**: 8086
- **Puerto Base de Datos**: 3312
- **Puerto phpMyAdmin**: 8089
- **Certificados SSL**: /etc/letsencrypt/live/motus.grupopcr.com.pa/

¡Tu aplicación BAES estará lista para producción! 🎉
