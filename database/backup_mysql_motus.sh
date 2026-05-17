#!/usr/bin/env bash
set -euo pipefail

# Backup automático de MySQL (contenedor motus_db)
# - Genera dump .sql.gz + checksum .sha256
# - Limpia respaldos antiguos por días de retención
# - Guarda logs por ejecución

DB_CONTAINER="${DB_CONTAINER:-motus_db}"
DB_NAME="${DB_NAME:-motus_baes}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-motus_root_2024}"
BACKUP_DIR="${BACKUP_DIR:-/root/backups/motus_db}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
DATE_TAG="$(date +%F_%H%M%S)"

mkdir -p "${BACKUP_DIR}"
mkdir -p "${BACKUP_DIR}/logs"

OUT_SQL_GZ="${BACKUP_DIR}/${DB_NAME}_${DATE_TAG}.sql.gz"
OUT_SHA="${OUT_SQL_GZ}.sha256"
LOG_FILE="${BACKUP_DIR}/logs/backup_${DATE_TAG}.log"

{
  echo "[INFO] $(date -Is) Iniciando backup de ${DB_NAME}..."
  echo "[INFO] Contenedor: ${DB_CONTAINER}"
  echo "[INFO] Destino: ${OUT_SQL_GZ}"

  # Dump consistente para InnoDB
  docker exec -i "${DB_CONTAINER}" mysqldump \
    -u"${DB_USER}" -p"${DB_PASS}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    "${DB_NAME}" | gzip -9 > "${OUT_SQL_GZ}"

  if [[ ! -s "${OUT_SQL_GZ}" ]]; then
    echo "[ERROR] Backup vacío o no generado."
    exit 1
  fi

  sha256sum "${OUT_SQL_GZ}" > "${OUT_SHA}"
  echo "[INFO] SHA256 generado: ${OUT_SHA}"

  # Limpieza por retención
  find "${BACKUP_DIR}" -maxdepth 1 -type f -name "${DB_NAME}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print -delete || true
  find "${BACKUP_DIR}" -maxdepth 1 -type f -name "${DB_NAME}_*.sql.gz.sha256" -mtime +"${RETENTION_DAYS}" -print -delete || true
  find "${BACKUP_DIR}/logs" -maxdepth 1 -type f -name "backup_*.log" -mtime +"${RETENTION_DAYS}" -print -delete || true

  # Últimos 5 backups
  echo "[INFO] Últimos backups:"
  ls -1t "${BACKUP_DIR}/${DB_NAME}_"*.sql.gz 2>/dev/null | head -n 5 || true

  echo "[OK] $(date -Is) Backup completado."
} | tee -a "${LOG_FILE}"

