CREATE DATABASE IF NOT EXISTS app_taller
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE app_taller;

CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol_id TINYINT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_roles FOREIGN KEY (rol_id) REFERENCES roles(id)
);

CREATE TABLE IF NOT EXISTS ordenes_trabajo (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente VARCHAR(120) NOT NULL,
  vehiculo VARCHAR(120) NOT NULL,
  patente VARCHAR(20) NOT NULL,
  descripcion TEXT NULL,
  estado ENUM('abierta', 'en_progreso', 'cerrada') NOT NULL DEFAULT 'abierta',
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS marcas (
  id_marca INT AUTO_INCREMENT PRIMARY KEY,
  nombre_marca VARCHAR(100) NOT NULL,
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS vehiculos_marcas (
  id_vehiculo_marca INT AUTO_INCREMENT PRIMARY KEY,
  nombre_marca_v VARCHAR(100) NOT NULL,
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS vehiculos_modelos (
  id_modelo INT AUTO_INCREMENT PRIMARY KEY,
  id_vehiculo_marca INT NOT NULL,
  nombre_modelo VARCHAR(100) NOT NULL,
  activo TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_vehiculos_modelos_marca FOREIGN KEY (id_vehiculo_marca) REFERENCES vehiculos_marcas(id_vehiculo_marca)
);

CREATE TABLE IF NOT EXISTS motorizaciones (
  id_motorizacion INT AUTO_INCREMENT PRIMARY KEY,
  nombre_motor VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS categorias (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nombre_categoria VARCHAR(100) NOT NULL,
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS proveedores (
  id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
  razon_social VARCHAR(150) NOT NULL,
  cuit VARCHAR(20) NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS unidades (
  id_unidad INT AUTO_INCREMENT PRIMARY KEY,
  nombre_unidad VARCHAR(60) NOT NULL,
  abreviatura VARCHAR(10) NOT NULL,
  activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS repuestos (
  id_repuesto INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(50) NOT NULL UNIQUE,
  cod_oem VARCHAR(100) NULL,
  nombre VARCHAR(150) NOT NULL,
  id_marca INT NULL,
  id_categoria INT NULL,
  id_unidad INT NULL,
  id_ubicacion INT NULL,
  id_proveedor INT NULL,
  stock_actual INT DEFAULT 0,
  stock_minimo INT DEFAULT 5,
  precio_costo DECIMAL(10,2) NULL,
  fecha_ingreso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activo TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_repuestos_marca FOREIGN KEY (id_marca) REFERENCES marcas(id_marca),
  CONSTRAINT fk_repuestos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
  CONSTRAINT fk_repuestos_unidad FOREIGN KEY (id_unidad) REFERENCES unidades(id_unidad),
  CONSTRAINT fk_repuestos_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor)
);

ALTER TABLE repuestos
  ADD COLUMN IF NOT EXISTS id_unidad INT NULL AFTER id_categoria;

CREATE TABLE IF NOT EXISTS compatibilidad_vehiculos (
  id_compatibilidad INT AUTO_INCREMENT PRIMARY KEY,
  id_repuesto INT NOT NULL,
  id_modelo INT NOT NULL,
  id_motorizacion INT NULL,
  anio_inicio INT NULL,
  anio_fin INT NULL,
  activo TINYINT(1) DEFAULT 1,
  CONSTRAINT fk_compat_repuesto FOREIGN KEY (id_repuesto) REFERENCES repuestos(id_repuesto),
  CONSTRAINT fk_compat_modelo FOREIGN KEY (id_modelo) REFERENCES vehiculos_modelos(id_modelo),
  CONSTRAINT fk_compat_motor FOREIGN KEY (id_motorizacion) REFERENCES motorizaciones(id_motorizacion)
);

INSERT INTO roles (nombre)
VALUES ('Admin')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO marcas (nombre_marca, activo)
SELECT 'Bosch', 1
WHERE NOT EXISTS (SELECT 1 FROM marcas WHERE nombre_marca = 'Bosch');

INSERT INTO marcas (nombre_marca, activo)
SELECT 'Valeo', 1
WHERE NOT EXISTS (SELECT 1 FROM marcas WHERE nombre_marca = 'Valeo');

INSERT INTO marcas (nombre_marca, activo)
SELECT 'SKF', 1
WHERE NOT EXISTS (SELECT 1 FROM marcas WHERE nombre_marca = 'SKF');

INSERT INTO vehiculos_marcas (nombre_marca_v, activo)
SELECT 'Toyota', 1
WHERE NOT EXISTS (SELECT 1 FROM vehiculos_marcas WHERE nombre_marca_v = 'Toyota');

INSERT INTO vehiculos_marcas (nombre_marca_v, activo)
SELECT 'Ford', 1
WHERE NOT EXISTS (SELECT 1 FROM vehiculos_marcas WHERE nombre_marca_v = 'Ford');

INSERT INTO vehiculos_marcas (nombre_marca_v, activo)
SELECT 'Volkswagen', 1
WHERE NOT EXISTS (SELECT 1 FROM vehiculos_marcas WHERE nombre_marca_v = 'Volkswagen');

INSERT INTO vehiculos_modelos (id_vehiculo_marca, nombre_modelo, activo)
SELECT vm.id_vehiculo_marca, 'Corolla', 1
FROM vehiculos_marcas vm
WHERE vm.nombre_marca_v = 'Toyota'
AND NOT EXISTS (
  SELECT 1 FROM vehiculos_modelos m
  WHERE m.id_vehiculo_marca = vm.id_vehiculo_marca AND m.nombre_modelo = 'Corolla'
);

INSERT INTO vehiculos_modelos (id_vehiculo_marca, nombre_modelo, activo)
SELECT vm.id_vehiculo_marca, 'Focus', 1
FROM vehiculos_marcas vm
WHERE vm.nombre_marca_v = 'Ford'
AND NOT EXISTS (
  SELECT 1 FROM vehiculos_modelos m
  WHERE m.id_vehiculo_marca = vm.id_vehiculo_marca AND m.nombre_modelo = 'Focus'
);

INSERT INTO vehiculos_modelos (id_vehiculo_marca, nombre_modelo, activo)
SELECT vm.id_vehiculo_marca, 'Golf', 1
FROM vehiculos_marcas vm
WHERE vm.nombre_marca_v = 'Volkswagen'
AND NOT EXISTS (
  SELECT 1 FROM vehiculos_modelos m
  WHERE m.id_vehiculo_marca = vm.id_vehiculo_marca AND m.nombre_modelo = 'Golf'
);

INSERT INTO motorizaciones (nombre_motor, descripcion, activo)
SELECT '1.6 Nafta', 'Motor naftero 1.6', 1
WHERE NOT EXISTS (SELECT 1 FROM motorizaciones WHERE nombre_motor = '1.6 Nafta');

INSERT INTO motorizaciones (nombre_motor, descripcion, activo)
SELECT '2.0 Diesel', 'Motor diesel 2.0', 1
WHERE NOT EXISTS (SELECT 1 FROM motorizaciones WHERE nombre_motor = '2.0 Diesel');

INSERT INTO motorizaciones (nombre_motor, descripcion, activo)
SELECT '1.4 Turbo', 'Motor turbo 1.4', 1
WHERE NOT EXISTS (SELECT 1 FROM motorizaciones WHERE nombre_motor = '1.4 Turbo');

INSERT INTO categorias (nombre_categoria, activo)
SELECT 'Filtros', 1
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre_categoria = 'Filtros');

INSERT INTO categorias (nombre_categoria, activo)
SELECT 'Frenos', 1
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre_categoria = 'Frenos');

INSERT INTO categorias (nombre_categoria, activo)
SELECT 'Encendido', 1
WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre_categoria = 'Encendido');

INSERT INTO proveedores (razon_social, cuit, activo)
SELECT 'Repuestos Centro', '30-12345678-9', 1
WHERE NOT EXISTS (SELECT 1 FROM proveedores WHERE razon_social = 'Repuestos Centro');

INSERT INTO proveedores (razon_social, cuit, activo)
SELECT 'AutoPartes Sur', '30-98765432-1', 1
WHERE NOT EXISTS (SELECT 1 FROM proveedores WHERE razon_social = 'AutoPartes Sur');

INSERT INTO unidades (nombre_unidad, abreviatura, activo)
SELECT 'Unidad', 'u', 1
WHERE NOT EXISTS (SELECT 1 FROM unidades WHERE nombre_unidad = 'Unidad');

INSERT INTO unidades (nombre_unidad, abreviatura, activo)
SELECT 'Caja', 'cj', 1
WHERE NOT EXISTS (SELECT 1 FROM unidades WHERE nombre_unidad = 'Caja');

INSERT INTO unidades (nombre_unidad, abreviatura, activo)
SELECT 'Litro', 'l', 1
WHERE NOT EXISTS (SELECT 1 FROM unidades WHERE nombre_unidad = 'Litro');

INSERT INTO usuarios (username, password, rol_id, activo)
SELECT 'Admin', '123456', r.id, 1
FROM roles r
WHERE r.nombre = 'Admin'
ON DUPLICATE KEY UPDATE
  password = VALUES(password),
  rol_id = VALUES(rol_id),
  activo = VALUES(activo);

INSERT INTO ordenes_trabajo (cliente, vehiculo, patente, descripcion, estado)
SELECT 'Cliente Demo', 'Toyota Corolla', 'AA123BB', 'Cambio de aceite y filtros', 'abierta'
WHERE NOT EXISTS (
  SELECT 1
  FROM ordenes_trabajo
  WHERE cliente = 'Cliente Demo' AND patente = 'AA123BB'
);

INSERT INTO repuestos (
  sku,
  cod_oem,
  nombre,
  id_marca,
  id_categoria,
  id_unidad,
  id_proveedor,
  stock_actual,
  stock_minimo,
  precio_costo,
  activo
)
SELECT
  'FIL-001',
  'OEM-001',
  'Filtro de aceite Corolla',
  m.id_marca,
  c.id_categoria,
  u.id_unidad,
  p.id_proveedor,
  12,
  3,
  18500.00,
  1
FROM marcas m
INNER JOIN categorias c ON c.nombre_categoria = 'Filtros'
INNER JOIN unidades u ON u.nombre_unidad = 'Unidad'
INNER JOIN proveedores p ON p.razon_social = 'Repuestos Centro'
WHERE m.nombre_marca = 'Bosch'
AND NOT EXISTS (
  SELECT 1 FROM repuestos WHERE sku = 'FIL-001'
);

INSERT INTO compatibilidad_vehiculos (
  id_repuesto,
  id_modelo,
  id_motorizacion,
  anio_inicio,
  anio_fin,
  activo
)
SELECT
  r.id_repuesto,
  m.id_modelo,
  mot.id_motorizacion,
  2017,
  2022,
  1
FROM repuestos r
INNER JOIN vehiculos_marcas vm ON vm.nombre_marca_v = 'Toyota'
INNER JOIN vehiculos_modelos m ON m.id_vehiculo_marca = vm.id_vehiculo_marca AND m.nombre_modelo = 'Corolla'
INNER JOIN motorizaciones mot ON mot.nombre_motor = '1.6 Nafta'
WHERE r.sku = 'FIL-001'
AND NOT EXISTS (
  SELECT 1
  FROM compatibilidad_vehiculos cv
  WHERE cv.id_repuesto = r.id_repuesto
    AND cv.id_modelo = m.id_modelo
    AND cv.id_motorizacion = mot.id_motorizacion
);