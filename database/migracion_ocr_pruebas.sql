-- Tabla de pruebas para OCR de adjuntos
-- Ejecutar una sola vez en la base de datos principal, por ejemplo:
--   mysql -u usuario -p nombre_base < database/migracion_ocr_pruebas.sql

CREATE TABLE IF NOT EXISTS ocr_pruebas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_guardado VARCHAR(255) NOT NULL,
    ruta_relativa VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    texto_extraido LONGTEXT NULL DEFAULT NULL COMMENT 'Texto extraído por OCR o pdftotext',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ocr_pruebas_creado_en (creado_en),
    KEY idx_ocr_pruebas_mime (mime_type)
);

