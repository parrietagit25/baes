# Sincronización por descarga (sin tocar Cloudflare)

Python **solo envía a GoDaddy**. Motus **descarga** los datos desde GoDaddy por cron (servidor a servidor, sin pasar por Cloudflare como cliente).

---

## 1. En GoDaddy: agregar exportación

1. Copia el archivo **`para_godaddy_api_web_export.php`** a tu servidor GoDaddy.
2. Súbelo como: **`api/api_web_export.php`** (misma carpeta donde está `api_web.php`).
3. Ajusta en ese archivo `$host`, `$usuario`, `$contraseña`, `$dbname` si no coinciden con los de tu `api_web.php`.
4. Prueba en el navegador (con el header de token no se puede; desde Motus o con Postman):
   - `https://automarketpanama.com/api/api_web_export.php` → devuelve JSON de `Automarket_Invs_web_temp`
   - `https://automarketpanama.com/api/api_web_export.php?table=web` → devuelve JSON de `Automarket_Invs_web`

---

## 2. En Motus (Digital Ocean): cron que descarga

1. Sube **`cron_pull_from_godaddy.php`** a `inventario_web/` en el servidor Motus (ya está en el proyecto).
2. Opcional: define la URL de exportación y el token por entorno (si no, usa la URL por defecto y el token del config):
   - `GODADDY_EXPORT_URL=https://automarketpanama.com/api/api_web_export.php`
   - `GODADDY_EXPORT_TOKEN=<mismo token>`
3. Programa un cron en el servidor Motus, por ejemplo cada 15 minutos:

```bash
# Cada 15 min (ajusta la ruta a tu instalación)
*/15 * * * * cd /var/www/html/inventario_web && php cron_pull_from_godaddy.php >> /var/www/html/inventario_web/cron_pull.log 2>&1
```

O cada hora:

```bash
0 * * * * cd /var/www/html/inventario_web && php cron_pull_from_godaddy.php >> /var/www/html/inventario_web/cron_pull.log 2>&1
```

4. Después del pull, si quieres pasar de _temp a _web en Motus, ejecuta también el pase por cron (en el mismo servidor no hay Cloudflare):

```bash
# Ejemplo: 5 min después del pull
5,20,35,50 * * * * curl -s "https://motus.automarket.com.pa/inventario_web/api_web_pasar_data.php" >> /var/www/html/inventario_web/cron_pasar.log 2>&1
```

O llama a `api_web_pasar_data.php` desde un script PHP en cron si lo prefieres.

---

## 3. Python: solo GoDaddy

En **autosdisponiblesweb.py** deja solo el envío a GoDaddy (quita el envío a Motus). Motus se actualiza solo vía cron.

Si quieres, puedes eliminar la parte de Motus del script Python y dejar solo:

```python
api_url_godaddy = 'https://automarketpanama.com/api/api_web.php'
# ... construir vehicles_list ...
requests.post(api_url_godaddy, json=vehicles_list, headers=headers, timeout=120)
```

---

## Resumen

| Quién        | Acción                          |
|-------------|----------------------------------|
| Python      | Envía solo a GoDaddy            |
| GoDaddy     | Recibe y expone JSON (api_web_export.php) |
| Motus cron  | Descarga JSON de GoDaddy → escribe en _temp |
| Motus cron  | Opcional: api_web_pasar_data.php (temp → web) |

No hace falta configurar Cloudflare: las peticiones a Motus las hace el propio servidor Motus (cron), no Python.
