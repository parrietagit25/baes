# Permisos para subir adjuntos (Ubuntu + Docker)

El directorio donde se guardan los archivos es: **`adjuntos/solicitudes/`** (dentro del proyecto).

---

## Opción 1: Ejecutar comandos **dentro** del contenedor PHP

Entra al contenedor y crea el directorio con permisos para el usuario del servidor web (normalmente `www-data`):

```bash
# Listar contenedores y localizar el de PHP (ej. motus_php)
docker ps

# Entrar al contenedor PHP (ajusta el nombre si es distinto)
docker exec -it motus_php bash

# Dentro del contenedor: ir al directorio del proyecto (ajusta si tu app está en otra ruta)
cd /var/www/html
# Si tu proyecto está en otra ruta, por ejemplo:
# cd /app

# Crear la carpeta de adjuntos si no existe
mkdir -p adjuntos/solicitudes

# Dar propiedad al usuario del servidor web (www-data)
chown -R www-data:www-data adjuntos

# Dar permisos de lectura, escritura y ejecución al propietario y grupo
chmod -R 775 adjuntos

# Salir del contenedor
exit
```

---

## Opción 2: Ejecutar comandos en el **host** (Ubuntu)

Si el proyecto está en tu máquina (ej. `~/baes`) y lo montas en Docker, el directorio `adjuntos` está en el host. El contenedor suele usar el usuario **www-data** (UID **33**). Para que ese usuario pueda escribir desde el contenedor:

```bash
# Ir al directorio del proyecto en el host
cd ~/baes
# O la ruta donde tengas el proyecto, ej: cd /home/tu_usuario/baes

# Crear la carpeta si no existe
mkdir -p adjuntos/solicitudes

# Opción A: Dar permisos amplios (cualquier usuario del contenedor puede escribir)
chmod -R 777 adjuntos

# Opción B (recomendada): Propiedad al UID 33 (www-data) y permisos 775
sudo chown -R 33:33 adjuntos
sudo chmod -R 775 adjuntos
```

Si no sabes el UID de `www-data` dentro del contenedor:

```bash
docker exec motus_php id www-data
# Verás algo como: uid=33(www-data) gid=33(www-data)
```

Usa ese `uid` y `gid` en `chown` (ej. `sudo chown -R 33:33 adjuntos`).

---

## Opción 3: Crear el directorio en el **docker-compose** (recomendado a largo plazo)

Para que el directorio exista y con buenos permisos cada vez que levantes el contenedor, puedes añadir un `volumen` o un `entrypoint` que cree la carpeta. Ejemplo de entrypoint en el Dockerfile o en `docker-compose`:

En `docker-compose.yml` puedes añadir un comando que cree el directorio al iniciar:

```yaml
services:
  motus_php:
    image: ...
    volumes:
      - .:/var/www/html
    entrypoint: ["/bin/sh", "-c", "mkdir -p /var/www/html/adjuntos/solicitudes && chown -R www-data:www-data /var/www/html/adjuntos && exec docker-php-entrypoint apache2-foreground"]
```

(O crear un script `entrypoint.sh` que cree el directorio y luego ejecute el comando original.)

---

## Comprobar que funciona

1. Después de ejecutar los comandos, sube de nuevo un archivo desde la aplicación.
2. Si sigue fallando, revisa los logs del contenedor:
   ```bash
   docker logs motus_php --tail 50
   ```
3. Comprueba que el directorio existe y tiene permisos:
   ```bash
   docker exec motus_php ls -la /var/www/html/adjuntos/solicitudes
   ```
