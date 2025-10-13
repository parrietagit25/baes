-- =====================================================
-- TABLA DE ADJUNTOS DE SOLICITUDES
-- =====================================================
-- Esta tabla almacena los archivos adjuntos de las solicitudes de crédito

CREATE TABLE IF NOT EXISTS adjuntos_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_archivo VARCHAR(100) NOT NULL,
    tamaño_archivo INT NOT NULL,
    descripcion TEXT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_solicitud (solicitud_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_subida),
    
    -- Claves foráneas
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear directorio para adjuntos si no existe
-- Nota: Este comando debe ejecutarse manualmente en el servidor
-- mkdir -p adjuntos/solicitudes

