#!/usr/bin/env bash
set -euo pipefail

# Restore de respaldo .sql.gz hacia motus_baes
# Uso:
#   ./database/restore_mysql_motus.sh /root/backups/motus_db/motus_baes_2026-04-28_235900.sql.gz
#
# Nota: Este script REEMPLAZA la base destino.

if [[ $# -lt 1 ]]; then
  echo "Uso: $0 <archivo_backup.sql.gz> [nombre_base]"
  exit 1
fi

BACKUP_FILE="$1"
DB_NAME="${2:-motus_baes}"
DB_CONTAINER="${DB_CONTAINER:-motus_db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-motus_root_2024}"

if [[ ! -f "${BACKUP_FILE}" ]]; then
  echo "[ERROR] No existe el archivo: ${BACKUP_FILE}"
  exit 1
fi

SHA_FILE="${BACKUP_FILE}.sha256"
if [[ -f "${SHA_FILE}" ]]; then
  echo "[INFO] Verificando checksum..."
  sha256sum -c "${SHA_FILE}"
fi

echo "[INFO] Creando backup previo de seguridad..."
PRE_TAG="$(date +%F_%H%M%S)"
mkdir -p /root/backups/motus_db
docker exec -i "${DB_CONTAINER}" mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --routines --triggers "${DB_NAME}" > "/root/backups/motus_db/${DB_NAME}_pre_restore_${PRE_TAG}.sql" || true

echo "[INFO] Recreando base ${DB_NAME}..."
docker exec -i "${DB_CONTAINER}" mysql -u"${DB_USER}" -p"${DB_PASS}" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[INFO] Restaurando ${BACKUP_FILE} ..."
gunzip -c "${BACKUP_FILE}" | docker exec -i "${DB_CONTAINER}" mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}"

echo "[INFO] Verificación rápida..."
docker exec -i "${DB_CONTAINER}" mysql -u"${DB_USER}" -p"${DB_PASS}" -e "USE \`${DB_NAME}\`; SHOW TABLES; SELECT COUNT(*) AS total_solicitudes FROM solicitudes_credito;" || true

echo "[OK] Restore completado."

