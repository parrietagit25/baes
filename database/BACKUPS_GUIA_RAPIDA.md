# Backups automáticos (Motus)

## 1) Subir scripts al servidor

Después de `git push` en local:

```bash
cd /root/baes
git pull
chmod +x database/backup_mysql_motus.sh
chmod +x database/restore_mysql_motus.sh
```

## 2) Probar backup manual

```bash
DB_CONTAINER=motus_db DB_NAME=motus_baes DB_USER=root DB_PASS='motus_root_2024' \
BACKUP_DIR=/root/backups/motus_db RETENTION_DAYS=14 \
/root/baes/database/backup_mysql_motus.sh
```

Verifica:

```bash
ls -lh /root/backups/motus_db
ls -lh /root/backups/motus_db/logs | tail
```

## 3) Programar cron diario (03:30 AM)

```bash
crontab -e
```

Agregar línea:

```cron
30 3 * * * DB_CONTAINER=motus_db DB_NAME=motus_baes DB_USER=root DB_PASS='motus_root_2024' BACKUP_DIR=/root/backups/motus_db RETENTION_DAYS=14 /root/baes/database/backup_mysql_motus.sh >> /root/backups/motus_db/cron.log 2>&1
```

Ver cron instalado:

```bash
crontab -l
```

## 4) Restore (emergencia)

```bash
/root/baes/database/restore_mysql_motus.sh /root/backups/motus_db/motus_baes_YYYY-MM-DD_HHMMSS.sql.gz
```

## 5) Recomendado (semanal)

- Probar restore en una base temporal (`motus_baes_restore_test`) y validar `SHOW TABLES`.
- Confirmar que se crean archivos `.sql.gz` + `.sha256`.
- Confirmar que la retención borra respaldos viejos automáticamente.

