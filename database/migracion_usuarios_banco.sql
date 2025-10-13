-- =====================================================
-- MIGRACIÓN: AGREGAR CAMPO BANCO_ID A USUARIOS
-- =====================================================
-- Este script agrega el campo banco_id a la tabla usuarios para relacionar
-- usuarios banco con sus instituciones bancarias

-- Agregar columna banco_id a la tabla usuarios
ALTER TABLE usuarios 
ADD COLUMN banco_id INT NULL AFTER cargo;

-- Agregar índice para banco_id
ALTER TABLE usuarios 
ADD INDEX idx_banco (banco_id);

-- Agregar clave foránea para banco_id
ALTER TABLE usuarios 
ADD CONSTRAINT fk_usuarios_banco 
FOREIGN KEY (banco_id) REFERENCES bancos(id) ON DELETE SET NULL;

-- Comentarios de la migración
-- =====================================================
-- CAMBIOS REALIZADOS:
-- - Agregada columna banco_id a tabla usuarios
-- - Creado índice para mejorar rendimiento
-- - Agregada clave foránea para integridad referencial
-- 
-- NOTA: Los usuarios banco deben tener su banco_id asignado
-- para que aparezcan correctamente en la vista de solicitudes
-- =====================================================

