-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS sistema_usuarios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_usuarios;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    pais VARCHAR(100),
    cargo VARCHAR(100),
    telefono VARCHAR(20),
    id_cobrador VARCHAR(50),
    id_vendedor VARCHAR(50),
    activo TINYINT(1) DEFAULT 1,
    primer_acceso TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de relación usuario-roles (muchos a muchos)
CREATE TABLE usuario_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rol_id INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_rol (usuario_id, rol_id)
);

-- Insertar roles por defecto
INSERT INTO roles (nombre, descripcion) VALUES
('ROLE_ADMIN', 'Administrador del sistema con acceso completo'),
('ROLE_SUPERVISOR', 'Supervisor con acceso a reportes y gestión'),
('ROLE_USER', 'Usuario estándar con acceso básico'),
('ROLE_AM', 'Asistente de Marketing'),
('ROLE_VENDEDOR', 'Vendedor del sistema'),
('ROLE_COBRADOR', 'Cobrador del sistema');

-- Insertar usuario administrador por defecto
-- Contraseña: admin123 (hash generado con password_hash)
INSERT INTO usuarios (nombre, apellido, email, password, pais, cargo, activo, primer_acceso) VALUES
('Administrador', 'Sistema', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'México', 'Administrador del Sistema', 1, 0);

-- Asignar rol de administrador al usuario admin
INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (1, 1);
