-- =====================================================
-- TABLA DE BANCOS
-- =====================================================
-- Esta tabla almacena la información de los bancos del sistema

CREATE TABLE IF NOT EXISTS bancos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    descripcion TEXT,
    direccion TEXT,
    telefono VARCHAR(20),
    email VARCHAR(255),
    sitio_web VARCHAR(255),
    contacto_principal VARCHAR(255),
    telefono_contacto VARCHAR(20),
    email_contacto VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_nombre (nombre),
    INDEX idx_codigo (codigo),
    INDEX idx_activo (activo),
    INDEX idx_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar algunos bancos de ejemplo
INSERT INTO bancos (nombre, codigo, descripcion, direccion, telefono, email, sitio_web, contacto_principal, telefono_contacto, email_contacto) VALUES
('Banco General', 'BG', 'Banco General de Panamá', 'Av. Balboa, Panamá', '+507 227-5000', 'info@bgeneral.com', 'https://www.bgeneral.com', 'Juan Pérez', '+507 227-5001', 'jperez@bgeneral.com'),
('Banco Nacional de Panamá', 'BNP', 'Banco Nacional de Panamá', 'Calle 50, Panamá', '+507 227-6000', 'info@banconal.com', 'https://www.banconal.com', 'María González', '+507 227-6001', 'mgonzalez@banconal.com'),
('Caja de Ahorros', 'CA', 'Caja de Ahorros de Panamá', 'Av. Central, Panamá', '+507 227-7000', 'info@cajadeahorros.com', 'https://www.cajadeahorros.com', 'Carlos Rodríguez', '+507 227-7001', 'crodriguez@cajadeahorros.com'),
('Banistmo', 'BAN', 'Banistmo Panamá', 'Torre Banistmo, Panamá', '+507 227-8000', 'info@banistmo.com', 'https://www.banistmo.com', 'Ana Martínez', '+507 227-8001', 'amartinez@banistmo.com'),
('Global Bank', 'GB', 'Global Bank Corporation', 'Calle 50, Panamá', '+507 227-9000', 'info@globalbank.com', 'https://www.globalbank.com', 'Luis Fernández', '+507 227-9001', 'lfernandez@globalbank.com');
