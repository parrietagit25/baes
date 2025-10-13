-- =====================================================
-- SCRIPT DE MIGRACIÓN: SISTEMA DE SOLICITUDES DE CRÉDITO
-- =====================================================
-- Este script crea las tablas necesarias para el sistema de solicitudes de crédito
-- Ejecutar: php ejecutar_migraciones_simple.php

-- Crear tabla de solicitudes de crédito
CREATE TABLE IF NOT EXISTS solicitudes_credito (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relación con gestor
    gestor_id INT NOT NULL,
    
    -- Relación con banco (usuario banco asignado)
    banco_id INT NULL,
    
    -- Datos generales del cliente
    tipo_persona ENUM('Natural', 'Juridica') NOT NULL,
    nombre_cliente VARCHAR(255) NOT NULL,
    cedula VARCHAR(50) NOT NULL,
    edad INT,
    genero ENUM('Masculino', 'Femenino', 'Otro'),
    
    -- Información de contacto
    telefono VARCHAR(20),
    email VARCHAR(255),
    
    -- Dirección
    direccion TEXT,
    provincia VARCHAR(100),
    distrito VARCHAR(100),
    corregimiento VARCHAR(100),
    barriada VARCHAR(100),
    casa_edif VARCHAR(100),
    numero_casa_apto VARCHAR(50),
    
    -- Información familiar
    casado TINYINT(1) DEFAULT 0,
    hijos INT DEFAULT 0,
    
    -- Perfil financiero
    perfil_financiero ENUM('Asalariado', 'Jubilado', 'Independiente') NOT NULL,
    ingreso DECIMAL(15,2),
    tiempo_laborar VARCHAR(100),
    nombre_empresa_negocio VARCHAR(255),
    estabilidad_laboral DATE,
    fecha_constitucion DATE,
    
    -- Datos del vehículo
    marca_auto VARCHAR(100),
    modelo_auto VARCHAR(100),
    año_auto INT,
    kilometraje INT,
    precio_especial DECIMAL(15,2),
    abono_porcentaje DECIMAL(5,2),
    abono_monto DECIMAL(15,2),
    
    -- Análisis del gestor
    comentarios_gestor TEXT,
    
    -- Respuesta del banco
    ejecutivo_banco VARCHAR(255),
    respuesta_banco ENUM('Pendiente', 'Aprobado', 'Pre Aprobado', 'Rechazado') DEFAULT 'Pendiente',
    letra DECIMAL(15,2),
    plazo INT,
    abono_banco DECIMAL(15,2),
    promocion VARCHAR(255),
    comentarios_ejecutivo_banco TEXT,
    
    -- Respuesta del cliente
    respuesta_cliente ENUM('Pendiente', 'Acepta', 'Rechaza') DEFAULT 'Pendiente',
    motivo_respuesta TEXT,
    
    -- Fechas importantes
    fecha_envio_proforma DATE,
    fecha_firma_cliente DATE,
    fecha_poliza DATE,
    fecha_carta_promesa DATE,
    
    -- Comentarios adicionales
    comentarios_fi TEXT,
    
    -- Estado general
    estado ENUM('Nueva', 'En Revisión Banco', 'Aprobada', 'Rechazada', 'Completada') DEFAULT 'Nueva',
    
    -- Timestamps
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_gestor (gestor_id),
    INDEX idx_banco (banco_id),
    INDEX idx_estado (estado),
    INDEX idx_respuesta_banco (respuesta_banco),
    INDEX idx_fecha_creacion (fecha_creacion),
    INDEX idx_email (email),
    INDEX idx_cedula (cedula),
    
    -- Claves foráneas
    FOREIGN KEY (gestor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (banco_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de notas de solicitud (muro de tiempo)
CREATE TABLE IF NOT EXISTS notas_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_nota ENUM('Comentario', 'Actualización', 'Documento', 'Respuesta Banco', 'Respuesta Cliente') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    contenido TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_solicitud (solicitud_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_creacion),
    
    -- Claves foráneas
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de documentos de solicitud
CREATE TABLE IF NOT EXISTS documentos_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo_documento VARCHAR(100),
    tamaño_archivo INT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_solicitud (solicitud_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha_subida),
    
    -- Claves foráneas
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar roles adicionales necesarios para el sistema de solicitudes
INSERT IGNORE INTO roles (nombre, descripcion) VALUES
('ROLE_GESTOR', 'Gestor de crédito - puede crear y gestionar solicitudes'),
('ROLE_BANCO', 'Analista bancario - puede aprobar/rechazar solicitudes');

-- Crear tabla de estadísticas de importación CSV (opcional)
CREATE TABLE IF NOT EXISTS estadisticas_importacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_importacion DATE NOT NULL,
    total_importados INT DEFAULT 0,
    total_errores INT DEFAULT 0,
    archivo_original VARCHAR(255),
    usuario_id INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_fecha (fecha_importacion),
    INDEX idx_usuario (usuario_id),
    
    -- Claves foráneas
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentarios finales
-- =====================================================
-- TABLAS CREADAS:
-- - solicitudes_credito: Tabla principal de solicitudes
-- - notas_solicitud: Muro de tiempo para cada solicitud
-- - documentos_solicitud: Gestión de documentos adjuntos
-- - estadisticas_importacion: Estadísticas de importación CSV
-- 
-- ROLES AGREGADOS:
-- - ROLE_GESTOR: Para crear y gestionar solicitudes
-- - ROLE_BANCO: Para analizar y responder solicitudes
-- 
-- PRÓXIMOS PASOS:
-- 1. Asignar roles ROLE_GESTOR y ROLE_BANCO a usuarios
-- 2. Crear solicitudes de prueba
-- 3. Probar el flujo completo del sistema
-- =====================================================

