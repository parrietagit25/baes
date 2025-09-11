-- Script para insertar usuario administrador con contraseña correctamente encriptada
-- Ejecutar este script después de crear las tablas

-- Primero, eliminar el usuario admin existente si existe
DELETE FROM usuario_roles WHERE usuario_id = 1;
DELETE FROM usuarios WHERE id = 1;

-- Resetear el auto-increment
ALTER TABLE usuarios AUTO_INCREMENT = 1;

-- Insertar usuario administrador con contraseña 'admin123' encriptada
-- Esta contraseña se generó con password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO usuarios (nombre, apellido, email, password, pais, cargo, telefono, activo, primer_acceso) VALUES 
('Administrador', 'Sistema', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'México', 'Administrador del Sistema', '', 1, 0);

-- Asignar rol de administrador
INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (1, 1);

-- Verificar que se insertó correctamente
SELECT 
    u.id,
    u.nombre,
    u.apellido,
    u.email,
    u.activo,
    GROUP_CONCAT(r.nombre) as roles
FROM usuarios u
LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
LEFT JOIN roles r ON ur.rol_id = r.id
WHERE u.email = 'admin@sistema.com'
GROUP BY u.id;
