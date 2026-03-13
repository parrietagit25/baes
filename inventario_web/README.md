# Inventario Web (datos desde Python)

Este subdirectorio recibe los datos de inventario que envía el proceso Python y los guarda en la **misma base de datos** del proyecto (motus_baes / solicitud_credito).

## Tablas

- **Automarket_Invs_web_temp**: tabla temporal donde llegan los datos desde Python.
- **Automarket_Invs_web**: tabla principal; se actualiza con el “pase” desde `_temp`.

Crear las tablas ejecutando el SQL:

```bash
# En la base de datos del proyecto (motus_baes o la que uses)
mysql -u usuario -p nombre_bd < ../database/automarket_invs_web.sql
```

O importar `database/automarket_invs_web.sql` desde phpMyAdmin.

## Endpoints

### 1. `api_web.php` – Recibir datos de Python

- **URL:** `https://tu-dominio.com/inventario_web/api_web.php`
- **Método:** POST
- **Header:** `X-Auth-Token: <token>`
- **Body:** JSON (objeto o array de vehículos con campos: VIN, Year, Make, Model, etc.)

El proceso Python debe apuntar a esta URL (además o en lugar de GoDaddy) para que los datos se guarden también en esta base.

### 2. `api_web_pasar_data.php` – Pasar temp → tabla principal

- **URL:** `https://tu-dominio.com/inventario_web/api_web_pasar_data.php`
- **Método:** GET o POST
- **Condición:** Solo hace el pase si hay **al menos 50** registros en `Automarket_Invs_web_temp`.

Puedes llamarlo con un cron después de que Python haya subido los datos a `api_web.php`.

## Configuración

- **Base de datos:** Se usa la misma conexión que el resto del proyecto (`config/database.php`).
- **Token:** Por defecto se usa el mismo que en GoDaddy. Para cambiarlo:
  - Variable de entorno: `INVENTARIO_WEB_TOKEN=tu_token`
  - O en `config.php`: `define('INVENTARIO_WEB_TOKEN', 'tu_token');`

## Proceso Python

Configura una segunda URL hacia esta instalación (Digital Ocean / baes) para que, además de GoDaddy, envíe los datos aquí:

```python
# Ejemplo: enviar también a la base baes/motus
url_baes = "https://motus.automarket.com.pa/inventario_web/api_web.php"
headers = {"X-Auth-Token": "SI5dGxz/2/AqWkOYuz6t4r3KYGbqGxOj3MhT3T/hp!J6Du9ko=..."}
requests.post(url_baes, json=datos_vehiculos, headers=headers)
```

Luego, en el servidor baes, ejecutar el pase (manual o por cron):

```
GET https://motus.automarket.com.pa/inventario_web/api_web_pasar_data.php
```
