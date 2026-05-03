-- ============================================================
-- Tablas del módulo Financiamiento
-- Ejecutar en la base de datos que use el módulo (motus_baes, motus_financiamiento, etc.)
-- Uso: mysql -u usuario -p nombre_base < financiamiento/database.sql
-- ============================================================

-- 1) Usuarios del panel de financiamiento (login, ver_registros, usuarios.php)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS financiamiento_usuarios (
    id int(11) NOT NULL AUTO_INCREMENT,
    nombre varchar(120) NOT NULL,
    email varchar(255) NOT NULL,
    password_hash varchar(255) NOT NULL,
    activo tinyint(1) NOT NULL DEFAULT 1,
    fecha_creacion timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2) Registros del formulario público (api/solicitud_publica.php inserta aquí)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS financiamiento_registros (
    id int(11) NOT NULL AUTO_INCREMENT,
    fecha_creacion timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

    token_email varchar(255) DEFAULT NULL,
    ip varchar(45) DEFAULT NULL,
    email_vendedor varchar(255) DEFAULT NULL COMMENT 'Correo decodificado del enlace (vendedor)',
    id_vendedor int(11) DEFAULT NULL COMMENT 'ID en ejecutivos_ventas si el email estaba registrado',

    cliente_nombre varchar(200) DEFAULT NULL,
    cliente_estado_civil varchar(50) DEFAULT NULL,
    cliente_sexo varchar(20) DEFAULT NULL,
    cliente_id varchar(50) DEFAULT NULL,
    cliente_nacimiento date DEFAULT NULL,
    cliente_edad int(11) DEFAULT NULL,
    cliente_nacionalidad varchar(100) DEFAULT NULL,
    cliente_dependientes int(11) DEFAULT NULL,
    cliente_correo varchar(255) DEFAULT NULL,
    cliente_peso decimal(6,2) DEFAULT NULL,
    cliente_estatura decimal(5,2) DEFAULT NULL,

    vivienda varchar(100) DEFAULT NULL,
    vivienda_monto decimal(12,2) DEFAULT NULL,
    prov_dist_corr varchar(200) DEFAULT NULL,
    tel_residencia varchar(50) DEFAULT NULL,
    barriada_calle_casa varchar(500) DEFAULT NULL,
    calle varchar(120) DEFAULT NULL,
    celular_cliente varchar(50) DEFAULT NULL,
    edificio_apto varchar(200) DEFAULT NULL,
    correo_residencial varchar(255) DEFAULT NULL,

    empresa_nombre varchar(200) DEFAULT NULL,
    empresa_ocupacion varchar(100) DEFAULT NULL,
    empresa_anios varchar(50) DEFAULT NULL,
    empresa_telefono varchar(50) DEFAULT NULL,
    empresa_salario decimal(14,2) DEFAULT NULL,
    empresa_direccion varchar(500) DEFAULT NULL,
    otros_ingresos varchar(500) DEFAULT NULL,
    ocupacion_otros varchar(200) DEFAULT NULL,
    trabajo_anterior varchar(500) DEFAULT NULL,

    tiene_conyuge tinyint(1) NOT NULL DEFAULT 0,
    con_nombre varchar(200) DEFAULT NULL,
    con_estado_civil varchar(50) DEFAULT NULL,
    con_sexo varchar(20) DEFAULT NULL,
    con_id varchar(50) DEFAULT NULL,
    con_nacimiento date DEFAULT NULL,
    con_edad int(11) DEFAULT NULL,
    con_nacionalidad varchar(100) DEFAULT NULL,
    con_dependientes int(11) DEFAULT NULL,
    con_correo varchar(255) DEFAULT NULL,
    con_empresa varchar(200) DEFAULT NULL,
    con_ocupacion varchar(100) DEFAULT NULL,
    con_anios varchar(50) DEFAULT NULL,
    con_tel varchar(50) DEFAULT NULL,
    con_salario decimal(14,2) DEFAULT NULL,
    con_direccion varchar(500) DEFAULT NULL,
    con_otros_ingresos varchar(500) DEFAULT NULL,
    con_trabajo_anterior varchar(500) DEFAULT NULL,

    refp1_nombre varchar(200) DEFAULT NULL,
    refp1_cel varchar(50) DEFAULT NULL,
    refp1_dir_res varchar(500) DEFAULT NULL,
    refp1_dir_lab varchar(500) DEFAULT NULL,
    refp2_nombre varchar(200) DEFAULT NULL,
    refp2_cel varchar(50) DEFAULT NULL,
    refp2_dir_res varchar(500) DEFAULT NULL,
    refp2_dir_lab varchar(500) DEFAULT NULL,
    reff1_nombre varchar(200) DEFAULT NULL,
    reff1_cel varchar(50) DEFAULT NULL,
    reff1_dir_res varchar(500) DEFAULT NULL,
    reff1_dir_lab varchar(500) DEFAULT NULL,
    reff2_nombre varchar(200) DEFAULT NULL,
    reff2_cel varchar(50) DEFAULT NULL,
    reff2_dir_res varchar(500) DEFAULT NULL,
    reff2_dir_lab varchar(500) DEFAULT NULL,

    marca_auto varchar(100) DEFAULT NULL,
    modelo_auto varchar(150) DEFAULT NULL,
    anio_auto int(11) DEFAULT NULL,
    kms_cod_auto int(11) DEFAULT NULL,
    precio_venta decimal(14,2) DEFAULT NULL,
    abono decimal(14,2) DEFAULT NULL,

    sucursal varchar(200) DEFAULT NULL,
    nombre_gestor varchar(200) DEFAULT NULL,
    comentarios_gestor text DEFAULT NULL,
    firma longtext DEFAULT NULL,
    firmantes_adicionales text DEFAULT NULL,
    telemetria_session_id varchar(100) DEFAULT NULL COMMENT 'Sesion del navegador del formulario publico',
    telemetria_started_at datetime DEFAULT NULL COMMENT 'Inicio del formulario en cliente',
    telemetria_submitted_at datetime DEFAULT NULL COMMENT 'Envio del formulario en cliente',
    telemetria_duracion_segundos int(11) DEFAULT NULL COMMENT 'Duracion total del llenado',
    telemetria_paso_tiempos_json longtext DEFAULT NULL COMMENT 'Duracion por paso del wizard (ms)',
    telemetria_eventos_json longtext DEFAULT NULL COMMENT 'Eventos del wizard (navegacion, errores, envio)',
    telemetria_dispositivo_json longtext DEFAULT NULL COMMENT 'Datos de dispositivo/navegador del cliente',
    telemetria_geo_country varchar(120) DEFAULT NULL COMMENT 'Pais por geolocalizacion IP (persistido al reportar)',
    telemetria_geo_city varchar(120) DEFAULT NULL COMMENT 'Ciudad por geolocalizacion IP (persistido al reportar)',
    solicitud_credito_id int(11) DEFAULT NULL COMMENT 'Solicitud Motus creada desde este envío (adjuntos)',

    PRIMARY KEY (id),
    KEY idx_fecha (fecha_creacion),
    KEY idx_cliente_id (cliente_id),
    KEY idx_cliente_correo (cliente_correo),
    KEY idx_fin_reg_solicitud_credito (solicitud_credito_id),
    KEY idx_id_vendedor (id_vendedor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Notas:
-- - financiamiento_usuarios: login del panel (login.php, usuarios.php).
--   Si la tabla está vacía, includes/auth.php puede crear un usuario
--   por defecto (admin@ejemplo.com / admin123).
-- - financiamiento_registros: lo llena api/solicitud_publica.php
--   cuando el formulario público (financiamiento/index.php) envía
--   los datos. Ver registros en financiamiento/ver_registros.php.
-- ============================================================
