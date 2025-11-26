# Actualizar Composer a Versión 2

## Problema
Estás usando Composer 1.10.1 (2020) que ya no tiene soporte. El soporte para Composer 1 se cerró el 1 de septiembre de 2025.

## Solución: Actualizar Composer

### Opción 1: Actualizar Composer Globalmente (Recomendado)

```bash
# Actualizar Composer a la versión 2
composer self-update --2

# Verificar versión
composer --version
```

### Opción 2: Reinstalar Composer 2

```bash
# Descargar e instalar Composer 2
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --2
php -r "unlink('composer-setup.php');"

# Mover a ubicación global (opcional)
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### Opción 3: Si estás en Docker

Si estás usando Docker, el Composer dentro del contenedor debería actualizarse automáticamente. Puedes:

```bash
# Reconstruir el contenedor
docker-compose build
docker-compose up -d

# O ejecutar composer dentro del contenedor
docker exec -it motus_php composer self-update --2
docker exec -it motus_php composer install
```

## Después de Actualizar

Una vez actualizado Composer a la versión 2:

```bash
# Instalar dependencias
composer install

# Verificar que PHPMailer se instaló
ls -la vendor/phpmailer/
```

## Nota sobre Ejecutar como Root

El mensaje "Do not run Composer as root" es una advertencia. Si necesitas ejecutar como root, puedes:

1. **Crear un usuario no-root** (recomendado):
```bash
sudo adduser ubuntu
sudo usermod -aG sudo ubuntu
su - ubuntu
cd /home/ubuntu/motus/baes
composer install
```

2. **O ignorar la advertencia** (no recomendado para producción):
```bash
COMPOSER_ALLOW_SUPERUSER=1 composer install
```

## Verificación

Después de instalar, verifica que todo funciona:

```bash
# Verificar que vendor existe
ls -la vendor/

# Verificar que PHPMailer está instalado
ls -la vendor/phpmailer/phpmailer/
```

