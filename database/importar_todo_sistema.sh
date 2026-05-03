#!/usr/bin/env bash
set -euo pipefail

# Importador total de base de datos MOTUS_BAES.
# - Recreate base
# - Importa tablas core + modulos
# - Aplica parches de compatibilidad idempotentes
# Uso:
#   chmod +x database/importar_todo_sistema.sh
#   ./database/importar_todo_sistema.sh

DB_CONTAINER="${DB_CONTAINER:-motus_db}"
DB_NAME="${DB_NAME:-motus_baes}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-motus_root_2024}"
PROJECT_ROOT="${PROJECT_ROOT:-/root/baes}"

MYSQL_DOCKER=(docker exec -i "$DB_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS")
MYSQL_DB_DOCKER=(docker exec -i "$DB_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME")

echo "[1/8] Backup de seguridad..."
docker exec -i "$DB_CONTAINER" mysqldump -u"$DB_USER" -p"$DB_PASS" --single-transaction --routines --triggers "$DB_NAME" > "/root/backup_${DB_NAME}_antes_import_total_$(date +%F_%H%M%S).sql" || true

echo "[2/8] Recreando base $DB_NAME..."
"${MYSQL_DOCKER[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[3/8] Importando esquema core completo..."
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/motus_baes_tabla_por_tabla.sql"

echo "[4/8] Importando tablas/modulos adicionales..."
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/financiamiento/database.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/automarket_invs_web.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/historial_solicitud.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/link_financiamiento.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/migracion_ocr_pruebas.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/migracion_adjuntos_financiamiento_registros.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/migracion_encuestas_satisfaccion.sql"
"${MYSQL_DB_DOCKER[@]}" < "$PROJECT_ROOT/database/migracion_configuracion_sistema.sql"

echo "[5/8] Parches de compatibilidad idempotentes (columnas/indices)..."
"${MYSQL_DOCKER[@]}" <<'SQL'
USE motus_baes;
SET @db := 'motus_baes';

-- Helper para ejecutar SQL condicional
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='calle')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN calle VARCHAR(120) NULL AFTER barriada_calle_casa',
  'SELECT "ok calle"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_session_id')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_session_id VARCHAR(100) NULL AFTER firmantes_adicionales',
  'SELECT "ok telemetria_session_id"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_started_at')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_started_at DATETIME NULL AFTER telemetria_session_id',
  'SELECT "ok telemetria_started_at"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_submitted_at')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_submitted_at DATETIME NULL AFTER telemetria_started_at',
  'SELECT "ok telemetria_submitted_at"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_duracion_segundos')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_duracion_segundos INT(11) NULL AFTER telemetria_submitted_at',
  'SELECT "ok telemetria_duracion_segundos"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_paso_tiempos_json')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_paso_tiempos_json LONGTEXT NULL AFTER telemetria_duracion_segundos',
  'SELECT "ok telemetria_paso_tiempos_json"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_eventos_json')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_eventos_json LONGTEXT NULL AFTER telemetria_paso_tiempos_json',
  'SELECT "ok telemetria_eventos_json"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_dispositivo_json')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_dispositivo_json LONGTEXT NULL AFTER telemetria_eventos_json',
  'SELECT "ok telemetria_dispositivo_json"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_geo_country')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_geo_country VARCHAR(120) NULL DEFAULT NULL COMMENT ''Pais por geolocalizacion IP (persistido)'' AFTER telemetria_dispositivo_json',
  'SELECT "ok telemetria_geo_country"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='telemetria_geo_city')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN telemetria_geo_city VARCHAR(120) NULL DEFAULT NULL COMMENT ''Ciudad por geolocalizacion IP (persistido)'' AFTER telemetria_geo_country',
  'SELECT "ok telemetria_geo_city"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='email_vendedor')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN email_vendedor VARCHAR(255) DEFAULT NULL COMMENT ''Correo decodificado del enlace (vendedor)'' AFTER ip',
  'SELECT "ok email_vendedor"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='id_vendedor')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN id_vendedor INT(11) DEFAULT NULL COMMENT ''ID en ejecutivos_ventas si el email estaba registrado'' AFTER email_vendedor',
  'SELECT "ok id_vendedor"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND INDEX_NAME='idx_id_vendedor')=0,
  'ALTER TABLE financiamiento_registros ADD KEY idx_id_vendedor (id_vendedor)',
  'SELECT "ok idx_id_vendedor"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND COLUMN_NAME='solicitud_credito_id')=0,
  'ALTER TABLE financiamiento_registros ADD COLUMN solicitud_credito_id INT NULL DEFAULT NULL COMMENT ''ID en solicitudes_credito generada desde este envío''',
  'SELECT "ok solicitud_credito_id"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='financiamiento_registros' AND INDEX_NAME='idx_fin_reg_solicitud_credito')=0,
  'ALTER TABLE financiamiento_registros ADD KEY idx_fin_reg_solicitud_credito (solicitud_credito_id)',
  'SELECT "ok idx_fin_reg_solicitud_credito"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='solicitudes_credito' AND COLUMN_NAME='ejecutivo_ventas_id')=0,
  'ALTER TABLE solicitudes_credito ADD COLUMN ejecutivo_ventas_id INT NULL AFTER banco_id',
  'SELECT "ok ejecutivo_ventas_id"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='solicitudes_credito' AND INDEX_NAME='idx_ejecutivo_ventas_id')=0,
  'ALTER TABLE solicitudes_credito ADD KEY idx_ejecutivo_ventas_id (ejecutivo_ventas_id)',
  'SELECT "ok idx_ejecutivo_ventas_id"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='solicitudes_credito' AND COLUMN_NAME='financiamiento_registro_id')=0,
  'ALTER TABLE solicitudes_credito ADD COLUMN financiamiento_registro_id INT NULL DEFAULT NULL COMMENT ''ID en financiamiento_registros''',
  'SELECT "ok financiamiento_registro_id"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='solicitudes_credito' AND INDEX_NAME='uq_solicitudes_financiamiento_registro')=0,
  'ALTER TABLE solicitudes_credito ADD UNIQUE KEY uq_solicitudes_financiamiento_registro (financiamiento_registro_id)',
  'SELECT "ok uq_solicitudes_financiamiento_registro"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='usuarios_banco_solicitudes' AND COLUMN_NAME='correos_enviados')=0,
  'ALTER TABLE usuarios_banco_solicitudes ADD COLUMN correos_enviados INT UNSIGNED NOT NULL DEFAULT 0 AFTER creado_por',
  'SELECT "ok correos_enviados"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='evaluaciones_banco' AND COLUMN_NAME='tasa_bancaria')=0,
  'ALTER TABLE evaluaciones_banco ADD COLUMN tasa_bancaria DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT ''Tasa nominal anual (%)'' AFTER promocion',
  'SELECT "ok tasa_bancaria"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='evaluaciones_banco' AND COLUMN_NAME='comentario_reevaluacion_solicitada')=0,
  'ALTER TABLE evaluaciones_banco ADD COLUMN comentario_reevaluacion_solicitada TEXT NULL DEFAULT NULL COMMENT ''Motivo indicado al pedir reevaluación'' AFTER comentarios',
  'SELECT "ok comentario_reevaluacion_solicitada"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='evaluaciones_banco' AND COLUMN_NAME='fecha_solicitud_reevaluacion')=0,
  'ALTER TABLE evaluaciones_banco ADD COLUMN fecha_solicitud_reevaluacion DATETIME NULL DEFAULT NULL AFTER comentario_reevaluacion_solicitada',
  'SELECT "ok fecha_solicitud_reevaluacion"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='email_resumen_banco_log')=0,
  'CREATE TABLE email_resumen_banco_log (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      solicitud_id INT NOT NULL,
      usuario_banco_id INT NULL,
      destinatario_email VARCHAR(255) NOT NULL,
      tipo_envio ENUM(''individual'',''todos'') NOT NULL DEFAULT ''individual'',
      estado ENUM(''enviado'',''fallido'') NOT NULL,
      provider VARCHAR(50) NULL,
      provider_message_id VARCHAR(191) NULL,
      mensaje VARCHAR(500) NULL,
      fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      INDEX idx_email_resumen_solicitud (solicitud_id),
      INDEX idx_email_resumen_estado (estado),
      INDEX idx_email_resumen_fecha (fecha_envio)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
  'SELECT "ok email_resumen_banco_log"'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

DROP TABLE IF EXISTS readme;
SQL

echo "[6/8] Dejando solo usuario administrador..."
"${MYSQL_DOCKER[@]}" <<'SQL'
USE motus_baes;
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE usuario_roles;
TRUNCATE usuarios;
INSERT INTO usuarios (id,nombre,apellido,email,password,pais,cargo,banco_id,telefono,id_cobrador,id_vendedor,activo,primer_acceso,fecha_creacion,fecha_actualizacion)
VALUES (1,'Administrador','Sistema','admin@sistema.com','$2y$10$NtEbfuJZktlHbn7qZukSIuJnxK5AZOdZghon8eGwWYcjwXwEdA8Pe','Panamá','Administrador del Sistema',NULL,NULL,NULL,NULL,1,0,NOW(),NOW());
INSERT INTO usuario_roles (id,usuario_id,rol_id,fecha_asignacion) VALUES (1,1,1,NOW());
SET FOREIGN_KEY_CHECKS=1;
SQL

echo "[7/8] Validaciones finales..."
"${MYSQL_DOCKER[@]}" -e "
USE $DB_NAME;
SHOW TABLES;
SHOW TABLES LIKE '%financiamiento%';
SHOW TABLES LIKE 'encuesta_%';
SHOW TABLES LIKE 'Automarket_Invs_web%';
SELECT id,nombre,apellido,email,activo FROM usuarios;
SELECT id,usuario_id,rol_id FROM usuario_roles;
"

echo "[8/8] Proceso completado."
echo "Base '$DB_NAME' recreada e importada. Usuario admin: admin@sistema.com"
