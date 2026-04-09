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

INSERT INTO roles (nombre)
VALUES ('Admin')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

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
