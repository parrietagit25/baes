-- ============================================================
-- Tablas Automarket_Invs_web_temp y Automarket_Invs_web
-- Compatible con inventario_web/api_web.php y api_web_pasar_data.php
-- Misma base de datos que motus_baes (solicitudes, etc.)
-- ============================================================

-- 1) Tabla temporal: recibe los datos desde Python (api_web.php)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Automarket_Invs_web_temp (
    Year            VARCHAR(10)    DEFAULT NULL,
    Transmission    VARCHAR(100)   DEFAULT NULL,
    Color           VARCHAR(100)   DEFAULT NULL,
    Make            VARCHAR(100)   DEFAULT NULL,
    Km              VARCHAR(50)    DEFAULT NULL,
    Code            VARCHAR(50)    DEFAULT NULL,
    LicensePlate    VARCHAR(50)    DEFAULT NULL,
    Model           VARCHAR(150)   DEFAULT NULL,
    Chasis          VARCHAR(100)   DEFAULT NULL,
    Unit            VARCHAR(50)    DEFAULT NULL,
    Engine          VARCHAR(100)   DEFAULT NULL,
    Fuel            VARCHAR(50)    DEFAULT NULL,
    Price           DECIMAL(14,2)  DEFAULT NULL,
    PriceTax        DECIMAL(14,2)  DEFAULT NULL,
    Doors           VARCHAR(10)    DEFAULT NULL,
    CarType         VARCHAR(100)   DEFAULT NULL,
    CC              VARCHAR(50)    DEFAULT NULL,
    LocationCode    VARCHAR(50)    DEFAULT NULL,
    LocationName    VARCHAR(150)   DEFAULT NULL,
    Interior        VARCHAR(100)   DEFAULT NULL,
    Headline        TEXT           DEFAULT NULL,
    Description     TEXT           DEFAULT NULL,
    Photo           VARCHAR(1000)  DEFAULT NULL,
    Status          VARCHAR(50)    DEFAULT NULL,
    Marked          TINYINT(1)     DEFAULT 0,
    Promo           TINYINT(1)     DEFAULT 0,
    PromoPrice      DECIMAL(14,2)  DEFAULT NULL,
    PromoPriceTax   DECIMAL(14,2)  DEFAULT NULL,
    LoadDate        DATETIME       DEFAULT NULL,
    Prefijo         VARCHAR(50)    DEFAULT NULL,
    VIN             VARCHAR(50)    NOT NULL,
    trg_updatefechaWeb VARCHAR(50) DEFAULT NULL,
    update_stat     INT            DEFAULT NULL,
    stat_master     INT            DEFAULT NULL,
    Internacional   VARCHAR(100)  DEFAULT '',
    tipo_compra     VARCHAR(100)   DEFAULT NULL,
    prioridad       INT            DEFAULT 0,
    foto_impel      VARCHAR(500)   DEFAULT '',
    FechaActualizacion TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (VIN),
    KEY idx_stat_master (stat_master),
    KEY idx_update_stat (update_stat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2) Tabla principal: destino del pase desde _temp (api_web_pasar_data.php)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Automarket_Invs_web (
    Year            VARCHAR(10)    DEFAULT NULL,
    Transmission    VARCHAR(100)   DEFAULT NULL,
    Color           VARCHAR(100)   DEFAULT NULL,
    Make            VARCHAR(100)   DEFAULT NULL,
    Km              VARCHAR(50)    DEFAULT NULL,
    Code            VARCHAR(50)    DEFAULT NULL,
    LicensePlate    VARCHAR(50)    DEFAULT NULL,
    Model           VARCHAR(150)   DEFAULT NULL,
    Chasis          VARCHAR(100)   DEFAULT NULL,
    Unit            VARCHAR(50)    DEFAULT NULL,
    Engine          VARCHAR(100)   DEFAULT NULL,
    Fuel            VARCHAR(50)    DEFAULT NULL,
    Price           DECIMAL(14,2)  DEFAULT NULL,
    PriceTax        DECIMAL(14,2)  DEFAULT NULL,
    Doors           VARCHAR(10)    DEFAULT NULL,
    CarType         VARCHAR(100)   DEFAULT NULL,
    CC              VARCHAR(50)    DEFAULT NULL,
    LocationCode    VARCHAR(50)    DEFAULT NULL,
    LocationName    VARCHAR(150)   DEFAULT NULL,
    Interior        VARCHAR(100)   DEFAULT NULL,
    Headline        TEXT           DEFAULT NULL,
    Description     TEXT           DEFAULT NULL,
    Photo           VARCHAR(1000)  DEFAULT NULL,
    Status          VARCHAR(50)    DEFAULT NULL,
    Marked          TINYINT(1)     DEFAULT 0,
    Promo           TINYINT(1)     DEFAULT 0,
    PromoPrice      DECIMAL(14,2)  DEFAULT NULL,
    PromoPriceTax   DECIMAL(14,2)  DEFAULT NULL,
    LoadDate        DATETIME       DEFAULT NULL,
    Prefijo         VARCHAR(50)    DEFAULT NULL,
    VIN             VARCHAR(50)    NOT NULL,
    trg_updatefechaWeb VARCHAR(50) DEFAULT NULL,
    update_stat     INT            DEFAULT NULL,
    stat_master     INT            DEFAULT NULL,
    Internacional   VARCHAR(100)  DEFAULT '',
    tipo_compra     VARCHAR(100)   DEFAULT NULL,
    prioridad       INT            DEFAULT 0,
    foto_impel      VARCHAR(500)   DEFAULT '',
    date_update     DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (VIN),
    KEY idx_status (Status),
    KEY idx_make_model (Make, Model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
