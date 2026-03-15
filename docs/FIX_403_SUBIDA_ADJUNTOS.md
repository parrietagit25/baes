# Solución: 403 al subir archivos (adjuntos)

El **403 Forbidden** no lo devuelve la aplicación: lo devuelve **Apache** (mod_security) o el **proxy (nginx)** delante del contenedor.

---

## 1. Si usas **nginx** como proxy delante de Docker

Edita la configuración del `server` o `location` que sirve `motus.automarket.com.pa` y asegúrate de:

- Permitir **POST** (no solo GET).
- Aumentar el tamaño máximo del body para subida de archivos.

Ejemplo:

```nginx
server {
    server_name motus.automarket.com.pa;
    # ...

    client_max_body_size 20M;

    location / {
        proxy_pass http://172.18.0.x:80;   # o el nombre del servicio Docker
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 20M;
    }
}
```

Reinicia nginx:

```bash
sudo nginx -t && sudo systemctl reload nginx
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

## 3. Comprobar qué devuelve el 403

Para ver si la respuesta 403 la envía **nginx** o **Apache**:

- Mira los logs de **nginx** (ej. `error.log`) en el momento de la subida.
- Mira los logs de **Apache** dentro del contenedor:  
  `docker exec motus_php tail -20 /var/log/apache2/error.log`

Si en los logs de Apache **no** aparece la petición POST a `adjuntos.php`, el 403 lo está devolviendo nginx (o un proxy anterior). Si sí aparece y aun así el cliente recibe 403, suele ser ModSecurity o alguna directiva de Apache.

---

## 4. Resumen

| Origen del 403 | Qué hacer |
|----------------|-----------|
| **Nginx**     | Añadir `client_max_body_size 20M;` y asegurar que el `location` permita POST y haga `proxy_pass` al contenedor. |
| **Apache (ModSecurity)** | Desactivar reglas o el motor solo para `/api/adjuntos.php` (SecRuleEngine Off o regla allow). |
| **Apache (límite body)** | Aumentar `LimitRequestBody` (ej. 52428800). |

Tras cambiar nginx o Apache, prueba de nuevo la subida desde el navegador.
