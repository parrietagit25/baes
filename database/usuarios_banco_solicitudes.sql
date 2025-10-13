-- Tabla para gestionar usuarios banco asignados a solicitudes
CREATE TABLE IF NOT EXISTS usuarios_banco_solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_banco_id INT NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_desactivacion TIMESTAMP NULL,
    creado_por INT NOT NULL,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_banco_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_asignacion (solicitud_id, usuario_banco_id)
);

-- Tabla para el muro de mensajes
CREATE TABLE IF NOT EXISTS mensajes_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('general', 'banco', 'gestor') DEFAULT 'general',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Índices para mejorar rendimiento
CREATE INDEX idx_usuarios_banco_solicitud ON usuarios_banco_solicitudes(solicitud_id);
CREATE INDEX idx_usuarios_banco_usuario ON usuarios_banco_solicitudes(usuario_banco_id);
CREATE INDEX idx_usuarios_banco_estado ON usuarios_banco_solicitudes(estado);
CREATE INDEX idx_mensajes_solicitud ON mensajes_solicitud(solicitud_id);
CREATE INDEX idx_mensajes_fecha ON mensajes_solicitud(fecha_creacion);
