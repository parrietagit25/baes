-- =====================================================
-- MIGRACIÓN: AGREGAR CAMPO VENDEDOR A SOLICITUDES
-- =====================================================
-- Este script agrega el campo vendedor_id a la tabla solicitudes_credito
-- para permitir la asignación de vendedores a las solicitudes

-- Agregar campo vendedor_id a la tabla solicitudes_credito
ALTER TABLE solicitudes_credito 
ADD COLUMN vendedor_id INT NULL AFTER banco_id,
ADD INDEX idx_vendedor (vendedor_id),
ADD CONSTRAINT fk_solicitudes_vendedor 
    FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Comentarios sobre el cambio
-- =====================================================
-- CAMPO AGREGADO:
-- - vendedor_id: Referencia al usuario vendedor asignado a la solicitud
-- 
-- PERMISOS DEL ROL VENDEDOR:
-- - Solo puede ver solicitudes asignadas a él
-- - Puede editar las solicitudes asignadas
-- - Puede cambiar el estado de las solicitudes
-- - NO puede eliminar solicitudes (solo admin)
-- 
-- ASIGNACIÓN:
-- - Solo los administradores pueden asignar vendedores
-- - Se hace desde el modal de edición de solicitud
-- =====================================================
