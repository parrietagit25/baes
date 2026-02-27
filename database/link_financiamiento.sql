-- Tabla para links de formulario de financiamiento (vendedor ingresa email, se genera link único)
-- Al enviar el formulario con ?t=TOKEN, se envía por correo al email_destino el PDF con lo llenado.

CREATE TABLE IF NOT EXISTS link_financiamiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_destino VARCHAR(255) NOT NULL COMMENT 'Correo del vendedor/gestor que recibe el PDF',
    token VARCHAR(64) NOT NULL UNIQUE,
    usuario_id INT NULL COMMENT 'Quien generó el link (vendedor/gestor/admin)',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email_destino),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
