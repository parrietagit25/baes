-- Historial de envíos de resumen a corredor (adjunto del formulario Corredor).
CREATE TABLE IF NOT EXISTS email_corredor_envios (
  id INT NOT NULL AUTO_INCREMENT,
  solicitud_id INT NOT NULL,
  usuario_id INT NOT NULL,
  email_corredor VARCHAR(255) NOT NULL,
  comentario_interno TEXT NULL,
  comentario_correo TEXT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  nombre_archivo VARCHAR(255) NOT NULL,
  ruta_archivo VARCHAR(500) NOT NULL,
  tipo_archivo VARCHAR(120) NULL,
  tamano_archivo INT NULL DEFAULT 0,
  estado ENUM('enviado','fallido') NOT NULL DEFAULT 'enviado',
  mensaje VARCHAR(500) NULL,
  fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_corredor_solicitud (solicitud_id),
  KEY idx_corredor_fecha (fecha_envio),
  CONSTRAINT fk_corredor_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito (id) ON DELETE CASCADE,
  CONSTRAINT fk_corredor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
