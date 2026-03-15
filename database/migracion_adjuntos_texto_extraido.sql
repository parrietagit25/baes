-- Añadir columna para almacenar el texto extraído por OCR de los adjuntos
-- Ejecutar una sola vez: mysql -u usuario -p nombre_base < database/migracion_adjuntos_texto_extraido.sql
--
-- Requisitos en el servidor para que se extraiga el texto automáticamente:
-- - Imágenes (JPG, PNG, GIF): Tesseract OCR
--   Linux: apt install tesseract-ocr tesseract-ocr-spa
--   Docker: idem en el contenedor PHP
-- - PDF (texto embebido): pdftotext (poppler-utils)
--   Linux: apt install poppler-utils
-- - PDF escaneados (solo imagen): pdftoppm + Tesseract
--   Incluido en poppler-utils + tesseract-ocr

ALTER TABLE adjuntos_solicitud
ADD COLUMN texto_extraido LONGTEXT NULL DEFAULT NULL COMMENT 'Texto extraído por OCR o pdftotext' AFTER descripcion;
