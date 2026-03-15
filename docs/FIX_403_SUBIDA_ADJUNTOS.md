# Solución: 403 al subir archivos (adjuntos)

El **403 Forbidden** no lo devuelve la aplicación: lo devuelve **Apache** (mod_security) o el **proxy (nginx)** delante del contenedor.

---

## 1. Si usas **nginx** como proxy delante de Docker (caso más habitual)

### Pasos exactos en el servidor

1. **Conectar por SSH** al servidor donde está nginx (ej. donde corre `motus.automarket.com.pa`).

2. **Editar el archivo correcto de nginx.** En tu servidor el archivo suele llamarse **`motus`** (no `motus.automarket.com.pa`). Comprueba con:
   ```bash
   ls /etc/nginx/sites-available/
   # Si ves "motus" y "default", el de la app es "motus"
   sudo nano /etc/nginx/sites-available/motus
   ```

3. **Comprueba** que en `sites-enabled` está enlazado el mismo archivo:
   ```bash
   ls -la /etc/nginx/sites-enabled/
   ```

4. **Añadir o comprobar** estas líneas en el `server` que tiene `listen 443 ssl` y `location /`:
   - Justo después de `server_name motus.automarket.com.pa;`: **`client_max_body_size 20M;`**
   - Dentro de `location / { ... }`: **`client_max_body_size 20M;`** y **`proxy_request_buffering off;`** (ayuda a que la subida no se rechace).

5. **Comprobar y recargar nginx:**
   ```bash
   sudo nginx -t && sudo systemctl reload nginx
   ```

6. **Si sigue el 403:** Reconstruir el contenedor PHP para que Apache use la config de subida (en el repo: `docker/apache-uploads.conf`):
   ```bash
   cd /home/ubuntu/motus/baes   # o la ruta de tu proyecto
   docker-compose build --no-cache motus_php && docker-compose up -d motus_php
   ```

7. **Probar de nuevo** la subida de archivos en la web.

### Archivo de ejemplo completo

En el repo hay un ejemplo listo para copiar: **`docs/nginx-ejemplo-motussubida.conf`**. Puedes usarlo como referencia o copiarlo al servidor y ajustar `server_name` y `proxy_pass` (puerto 8086 si el contenedor PHP está en ese puerto):

```bash
# En el servidor, desde la carpeta del proyecto:
sudo cp docs/nginx-ejemplo-motussubida.conf /etc/nginx/sites-available/motus.automarket.com.pa
# Ajustar server_name, SSL y proxy_pass si hace falta, luego:
sudo nginx -t && sudo systemctl reload nginx
```

Ejemplo mínimo de lo que debe tener el `server`:

```nginx
server {
    server_name motus.automarket.com.pa;
    client_max_body_size 20M;

    location / {
        proxy_pass http://127.0.0.1:8086;   # o la IP:puerto de motus_php
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 20M;
    }
}
```

---

## 2. Si el **403 viene de Apache** (contenedor PHP)

A veces **ModSecurity** bloquea peticiones POST con `multipart/form-data` (subida de archivos) y devuelve 403.

### Opción A: Desactivar ModSecurity solo para la API de adjuntos

Si en el contenedor tienes ModSecurity activo, crea o edita la config de Apache (ej. en el Dockerfile o en un volumen). Por ejemplo en `/etc/apache2/conf-available/security.conf` o en un vhost:

```apache
<Location "/api/adjuntos.php">
    SecRuleEngine Off
</Location>
```

O excluir solo la subida por cuerpo:

```apache
SecRule REQUEST_URI "@beginsWith /api/adjuntos.php" "id:1000,phase:1,nolog,allow,ctl:requestBodyLimit=52428800"
```

(Revisa la sintaxis según tu versión de ModSecurity.)

### Opción B: Aumentar límite de cuerpo en Apache

En la config de Apache del contenedor (o en un vhost):

```apache
LimitRequestBody 52428800
```

(52428800 = 50 MB.)

Reinicia Apache dentro del contenedor:

```bash
docker exec motus_php apache2ctl graceful
```

---

## 3. Archivo de prueba (diagnóstico rápido)

En el proyecto hay una prueba para ver en qué punto falla:

1. **Abre en el navegador** (misma URL donde falla la subida):  
   `https://motus.automarket.com.pa/test_upload.html`

2. **Ejecuta en orden:**
   - **Probar GET** → Si sale HTTP 200 y "GET llegó a PHP", la ruta `/api/` responde.
   - **Probar POST (sin archivo)** → Si sale 200, nginx/Apache permiten POST.
   - **Subir archivo de prueba** → Si aquí sale **403**, el bloqueo es al enviar archivo (nginx o Apache). Si sale **200**, el servidor permite subir y el problema puede ser solo en `adjuntos.php` (por ejemplo sesión o otra regla).

3. **Interpretación:**
   - GET 403 o no carga → problema de ruta o de nginx antes de PHP.
   - GET 200, POST sin archivo 403 → POST bloqueado en general.
   - GET 200, POST sin archivo 200, **POST con archivo 403** → bloqueo por subida (tamaño, multipart o proxy). Revisar `client_max_body_size`, `proxy_request_buffering off` y Apache.

**Eliminar en producción** cuando ya no lo necesites: `api/test_upload.php` y `test_upload.html`.

---

## 4. Comprobar qué devuelve el 403

Para ver si la respuesta 403 la envía **nginx** o **Apache**:

- Mira los logs de **nginx** (ej. `error.log`) en el momento de la subida.
- Mira los logs de **Apache** dentro del contenedor:  
  `docker exec motus_php tail -20 /var/log/apache2/error.log`

Si en los logs de Apache **no** aparece la petición POST a `adjuntos.php`, el 403 lo está devolviendo nginx (o un proxy anterior). Si sí aparece y aun así el cliente recibe 403, suele ser ModSecurity o alguna directiva de Apache.

---

## 5. Resumen

| Origen del 403 | Qué hacer |
|----------------|-----------|
| **Nginx**     | Añadir `client_max_body_size 20M;` y asegurar que el `location` permita POST y haga `proxy_pass` al contenedor. |
| **Apache (ModSecurity)** | Desactivar reglas o el motor solo para `/api/adjuntos.php` (SecRuleEngine Off o regla allow). |
| **Apache (límite body)** | Aumentar `LimitRequestBody` (ej. 52428800). |

Tras cambiar nginx o Apache, prueba de nuevo la subida desde el navegador.
