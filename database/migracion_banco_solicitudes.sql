-- =====================================================
-- MIGRACIÓN: AGREGAR CAMPO BANCO_ID A SOLICITUDES
-- =====================================================
-- Este script agrega el campo banco_id a la tabla solicitudes_credito existente

-- Agregar columna banco_id
ALTER TABLE solicitudes_credito 
ADD COLUMN banco_id INT NULL AFTER gestor_id;

-- Agregar índice para banco_id
ALTER TABLE solicitudes_credito 
ADD INDEX idx_banco (banco_id);

-- Agregar clave foránea para banco_id
ALTER TABLE solicitudes_credito 
ADD CONSTRAINT fk_solicitudes_banco 
FOREIGN KEY (banco_id) REFERENCES usuarios(id) ON DELETE SET NULL;
