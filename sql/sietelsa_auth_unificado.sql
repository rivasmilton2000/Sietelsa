CREATE DATABASE IF NOT EXISTS sietelsa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sietelsa;

CREATE TABLE IF NOT EXISTS perfiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(150) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS perfil_permiso (
  perfil_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (perfil_id, permiso_id),
  CONSTRAINT fk_perfil_permiso_perfil
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_perfil_permiso_permiso
    FOREIGN KEY (permiso_id) REFERENCES permisos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS modulos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_modulo VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(150) NULL,
  estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS perfil_modulo (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perfil_id INT UNSIGNED NOT NULL,
  modulo_id INT UNSIGNED NOT NULL,
  aprobado TINYINT(1) NOT NULL DEFAULT 1,
  fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_perfil_modulo (perfil_id, modulo_id),
  CONSTRAINT fk_perfil_modulo_perfil
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_perfil_modulo_modulo
    FOREIGN KEY (modulo_id) REFERENCES modulos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  telefono VARCHAR(30) NULL,
  direccion VARCHAR(200) NULL,
  foto_path VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  perfil_id INT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_perfil
    FOREIGN KEY (perfil_id) REFERENCES perfiles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS servicios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(120) NOT NULL,
  descripcion VARCHAR(1000) NULL,
  icono VARCHAR(80) NOT NULL DEFAULT 'fa-circle-info',
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portafolio (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(120) NOT NULL,
  descripcion VARCHAR(1200) NULL,
  imagen_path VARCHAR(255) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portafolio_imagenes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  portafolio_id INT UNSIGNED NOT NULL,
  imagen_path VARCHAR(255) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_portafolio_imagenes_portafolio
    FOREIGN KEY (portafolio_id) REFERENCES portafolio(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS proyectos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(120) NOT NULL,
  portafolio_id INT UNSIGNED NULL,
  imagen_path VARCHAR(255) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_proyectos_portafolio
    FOREIGN KEY (portafolio_id) REFERENCES portafolio(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS nosotros (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(120) NOT NULL,
  descripcion_1 TEXT NULL,
  descripcion_2 TEXT NULL,
  descripcion_3 TEXT NULL,
  imagen_path VARCHAR(255) NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contacto_mensajes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(40) NULL,
  asunto VARCHAR(180) NULL,
  mensaje TEXT NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'nuevo',
  respuesta_admin TEXT NULL,
  respondido_por_usuario_id INT UNSIGNED NULL,
  respondido_en DATETIME NULL,
  notificado TINYINT(1) NOT NULL DEFAULT 0,
  error_notificacion VARCHAR(255) NULL,
  ip_origen VARCHAR(45) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contacto_estado_creado (estado, creado_en),
  CONSTRAINT fk_contacto_respondido_por_usuario
    FOREIGN KEY (respondido_por_usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visitas_sitio (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pagina VARCHAR(120) NOT NULL DEFAULT 'inicio',
  ip_origen VARCHAR(45) NULL,
  pais_codigo CHAR(2) NULL,
  pais_nombre VARCHAR(120) NULL,
  user_agent VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visitas_creado (creado_en),
  INDEX idx_visitas_pagina_creado (pagina, creado_en),
  INDEX idx_visitas_pais_creado (pais_codigo, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuario_permiso (
  usuario_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  permitido TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (usuario_id, permiso_id),
  CONSTRAINT fk_usuario_permiso_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_permiso_permiso
    FOREIGN KEY (permiso_id) REFERENCES permisos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO perfiles (nombre, descripcion) VALUES
  ('admin', 'Control total del sistema')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO permisos (codigo, descripcion) VALUES
  ('ver_dashboard', 'Puede ingresar al dashboard'),
  ('gestionar_usuarios', 'Puede crear o editar usuarios'),
  ('ver_reportes', 'Puede ver reportes'),
  ('gestionar_contenido', 'Permiso general heredado para gestionar contenido'),
  ('gestionar_servicios', 'Puede administrar el modulo de servicios'),
  ('gestionar_portafolio', 'Puede administrar el modulo de portafolio'),
  ('gestionar_proyectos', 'Puede administrar el modulo de proyectos'),
  ('gestionar_nosotros', 'Puede administrar el modulo de nosotros'),
  ('gestionar_contacto', 'Puede gestionar los mensajes de contacto'),
  ('gestionar_backup', 'Puede generar y descargar respaldos SQL'),
  ('gestionar_perfiles', 'Puede crear, editar o eliminar perfiles')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO modulos (nombre_modulo, descripcion, estado)
SELECT p.codigo, p.descripcion, 'activo'
FROM permisos p
ON DUPLICATE KEY UPDATE
  descripcion = VALUES(descripcion),
  estado = VALUES(estado);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('ver_dashboard', 'gestionar_usuarios', 'ver_reportes', 'gestionar_contenido')
WHERE p.nombre = 'admin'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('gestionar_servicios', 'gestionar_portafolio', 'gestionar_proyectos', 'gestionar_nosotros', 'gestionar_contacto', 'gestionar_backup', 'gestionar_perfiles')
WHERE p.nombre = 'admin'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('ver_dashboard', 'gestionar_usuarios', 'ver_reportes', 'gestionar_contenido', 'gestionar_servicios', 'gestionar_portafolio', 'gestionar_proyectos', 'gestionar_nosotros', 'gestionar_contacto', 'gestionar_backup', 'gestionar_perfiles')
WHERE p.nombre = 'developer'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('ver_dashboard', 'ver_reportes', 'gestionar_contenido')
WHERE p.nombre = 'gerente'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('gestionar_servicios', 'gestionar_portafolio', 'gestionar_proyectos', 'gestionar_nosotros', 'gestionar_contacto')
WHERE p.nombre = 'gerente'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('ver_dashboard')
WHERE p.nombre = 'tecnico'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_permiso (perfil_id, permiso_id)
SELECT p.id, pm.id
FROM perfiles p
JOIN permisos pm ON pm.codigo IN ('ver_dashboard')
WHERE p.nombre = 'cliente'
ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id);

INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
SELECT pp.perfil_id, m.id, 1
FROM perfil_permiso pp
INNER JOIN permisos p ON p.id = pp.permiso_id
INNER JOIN modulos m ON BINARY m.nombre_modulo = BINARY p.codigo
ON DUPLICATE KEY UPDATE aprobado = VALUES(aprobado);

INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
SELECT p.id, m.id, 1
FROM perfiles p
INNER JOIN modulos m ON m.estado = 'activo'
WHERE p.nombre = 'admin'
ON DUPLICATE KEY UPDATE aprobado = VALUES(aprobado);
