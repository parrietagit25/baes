-- Rol ROLE_ADMIN_BANCO: ve todas las SC asignadas a usuarios de su misma entidad (usuarios.banco_id).
INSERT INTO roles (nombre, descripcion, activo)
SELECT 'ROLE_ADMIN_BANCO', 'Administrador de entidad bancaria: ve solicitudes de todos los usuarios de su banco', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ROLE_ADMIN_BANCO');
