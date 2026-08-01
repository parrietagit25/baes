-- Roles de supervisor de sucursal (SP) para reportes / solicitudes por alcance.
INSERT INTO roles (nombre, descripcion, activo)
SELECT 'ROLE_SP_NC', 'Supervisor nacional: ve todos los reportes y solicitudes de todos los vendedores/sucursales', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ROLE_SP_NC');

INSERT INTO roles (nombre, descripcion, activo)
SELECT 'ROLE_SP_TBM', 'Supervisor Tumbamuerto (TBM): reportes y solicitudes de vendedores TBM', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ROLE_SP_TBM');

INSERT INTO roles (nombre, descripcion, activo)
SELECT 'ROLE_SP_VIS', 'Supervisor Vía Israel (VIS): reportes y solicitudes de vendedores VIS', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ROLE_SP_VIS');

INSERT INTO roles (nombre, descripcion, activo)
SELECT 'ROLE_SP_CV', 'Supervisor Costa Verde (CV): reportes y solicitudes de vendedores CV', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ROLE_SP_CV');

INSERT INTO roles (nombre, descripcion, activo)
SELECT 'ROLE_SP_DV', 'Supervisor David/Chiriquí (CH): reportes y solicitudes de vendedores CH', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ROLE_SP_DV');
