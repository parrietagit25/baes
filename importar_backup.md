# Importar backup sin depender del progreso de phpMyAdmin

El mensaje **"No se pudo cargar el progreso de la importación"** suele ser solo un fallo de la barra de progreso; la importación puede haber **completado bien**.

---

## Opción recomendada: archivo tabla por tabla (sin errores de relaciones)

En la carpeta **`database/`** está el archivo **`motus_baes_tabla_por_tabla.sql`** que:

1. Crea la base y las tablas **sin** claves foráneas (así no falla por orden).
2. Inserta todos los datos en el orden correcto.
3. Al final agrega todas las relaciones (FK) con `ALTER TABLE`.

**En phpMyAdmin:** Importar → elegir `database/motus_baes_tabla_por_tabla.sql` → Continuar.

**Con Docker:** desde la raíz del proyecto:

```bash
docker exec -i motus_db mysql -u root -pmotus_root_2024 -e "source /docker-entrypoint-initdb.d/motus_baes_tabla_por_tabla.sql"
```

(Ese archivo ya está en `database/`, que está montado en el contenedor.)

---

## 1. Comprobar si ya se importó

1. En phpMyAdmin (http://localhost:8089) abre el panel izquierdo.
2. Busca la base **`motus_baes`**.
3. Haz clic en ella y revisa si ves las tablas: `usuarios`, `bancos`, `solicitudes_credito`, `roles`, etc.

Si están todas y con datos, **no hace falta volver a importar**.

---

## 2. Si no se importó: usar la consola (recomendado)

Así no dependes del progreso de phpMyAdmin.

### Paso A – Poner el backup en el proyecto

Copia el archivo del backup a la carpeta `database` del proyecto:

- **Desde:** `Desktop\server_ubunto_amazon\motus_baes_backup.sql`
- **Hacia:** `c:\xampp\htdocs\baes\database\motus_baes_backup.sql`

(O el path donde tengas el proyecto baes.)

### Paso B – Importar con Docker

Abre PowerShell o CMD en la carpeta del proyecto (`c:\xampp\htdocs\baes`) y ejecuta:

```bash
docker exec -i motus_db mysql -u root -pmotus_root_2024 -e "source /docker-entrypoint-initdb.d/motus_baes_backup.sql"
```

Si usas otro usuario/contraseña de MySQL, cambia `root` y `motus_root_2024` por los que tengas.

### Paso C – Comprobar

Entra de nuevo a phpMyAdmin y revisa que la base `motus_baes` tenga todas las tablas y datos.

---

## 3. Alternativa: importar por stdin (sin copiar a `database`)

Si prefieres no copiar el `.sql` al proyecto:

```powershell
Get-Content "C:\Users\pedro.arrieta\Desktop\server_ubunto_amazon\motus_baes_backup.sql" -Raw -Encoding UTF8 | docker exec -i motus_db mysql -u root -pmotus_root_2024
```

Ejecuta esto en PowerShell desde cualquier carpeta. La base se crea y se rellena con el contenido del backup.
