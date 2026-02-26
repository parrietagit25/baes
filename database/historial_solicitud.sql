-- Tabla de historial de cambios por solicitud
-- Ejecutar una sola vez en la base de datos

CREATE TABLE IF NOT EXISTS historial_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_accion VARCHAR(50) NOT NULL COMMENT 'creacion, cambio_estado, documento_agregado, asignacion_banco, actualizacion_datos, evaluacion_banco',
    descripcion TEXT NOT NULL,
    estado_anterior VARCHAR(100) DEFAULT NULL,
    estado_nuevo VARCHAR(100) DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_solicitud (solicitud_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_tipo (tipo_accion),
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
