-- email del vendedor (enlace) e id en ejecutivos_ventas (opcionales)
-- Aplicar en la base donde exista financiamiento_registros (ej. motus_baes o motus_financiamiento)

SET NAMES utf8mb4;

ALTER TABLE `financiamiento_registros`
  ADD COLUMN `email_vendedor` varchar(255) DEFAULT NULL COMMENT 'Correo decodificado del enlace (vendedor)' AFTER `ip`,
  ADD COLUMN `id_vendedor` int(11) DEFAULT NULL COMMENT 'ID en ejecutivos_ventas si el email estaba registrado' AFTER `email_vendedor`;

ALTER TABLE `financiamiento_registros` ADD KEY `idx_id_vendedor` (`id_vendedor`);
